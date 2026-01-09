<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\OAuth\Infrastructure\Http\Form\ProviderCredentialsType;
use App\OAuth\Infrastructure\Security\OAuthAuthorizationCodeStore;
use App\OAuth\Infrastructure\Security\RedirectUriValidator;
use App\Shared\Infrastructure\Security\EncryptionService;
use App\Shared\Infrastructure\Security\OAuthSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth 2.0 Authorization endpoint.
 * Handles user authentication via social providers and credential collection.
 */
final class AuthorizationController extends AbstractController
{
    public function __construct(
        private readonly OAuthSessionManager $oauthSession,
        private readonly UserRepository $userRepository,
        private readonly EncryptionService $encryptionService,
        private readonly OAuthAuthorizationCodeStore $codeStore,
        private readonly RedirectUriValidator $redirectUriValidator,
    ) {
    }

    /**
     * OAuth authorization endpoint.
     * Redirects to social auth provider for authentication.
     */
    #[Route('/oauth/authorize', name: 'oauth_authorize', methods: ['GET'])]
    public function authorize(Request $request): Response
    {
        $clientId = $request->query->getString('client_id');
        $redirectUri = $request->query->getString('redirect_uri');
        $state = $request->query->getString('state');

        if ('' === $clientId || '' === $redirectUri) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Missing client_id or redirect_uri'], 400);
        }

        // Security: validate redirect_uri against whitelist
        if (!$this->redirectUriValidator->isAllowed($redirectUri)) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'Invalid redirect_uri. Only localhost URLs are allowed for MCP clients.',
            ], 400);
        }

        $this->oauthSession->storeMcpOAuthParams($clientId, $redirectUri, $state);
        $authUrl = $this->oauthSession->startAuth();

        return $this->redirect($authUrl);
    }

    /**
     * OAuth callback endpoint.
     * Receives the user from provider, then shows form to enter provider credentials.
     * Also handles admin login flow and signup flow based on session flags.
     */
    #[Route('/oauth/callback', name: 'oauth_callback', methods: ['GET', 'POST'])]
    public function oauthCallback(Request $request): Response
    {
        // Handle GET: Provider redirects back with authorization code
        if ($request->isMethod('GET') && $request->query->has('code')) {
            $code = $request->query->getString('code');
            $state = $request->query->getString('state');

            if ('' === $code) {
                return new JsonResponse(['error' => 'access_denied', 'error_description' => 'User denied access or provider error'], 400);
            }

            try {
                $authUser = $this->oauthSession->handleCallback($code, $state);

                // Dispatch to appropriate handler based on session flags
                if ($this->oauthSession->isSignupFlow()) {
                    return $this->handleSignupCallback($authUser);
                }

                if ($this->oauthSession->isAdminLogin()) {
                    return $this->handleAdminLogin($authUser['email']);
                }

                // Default: MCP OAuth flow
                return $this->handleMcpOAuthCallback($authUser);
            } catch (\RuntimeException $e) {
                return new JsonResponse(['error' => 'server_error', 'error_description' => $e->getMessage()], 500);
            }
        }

        // Check session validity
        $userEmail = $this->oauthSession->getMcpUserEmail();
        if (null === $userEmail) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Session expired, please start authorization again'], 400);
        }

        // User must exist in database (created via invite link or by admin)
        $dbUser = $this->userRepository->findByEmail($userEmail);
        if (null === $dbUser) {
            return $this->render('oauth/not_registered.html.twig', [
                'email' => $userEmail,
            ]);
        }

        // User must be approved by admin
        if ($dbUser->isPending()) {
            return $this->render('oauth/pending_approval.html.twig', [
                'email' => $userEmail,
            ]);
        }

        $organization = $dbUser->getOrganization();
        $providerConfig = $organization->getProviderConfig();

        // If user already has credentials, auto-authorize
        if ($dbUser->hasProviderCredentials()) {
            $decryptedCredentials = json_decode(
                $this->encryptionService->decrypt($dbUser->getProviderCredentials()),
                true
            );

            return $this->completeAuthorizationWithCredentials(
                $providerConfig,
                $decryptedCredentials,
                $dbUser->getId()
            );
        }

        // User needs to enter their Redmine API key
        $form = $this->createForm(ProviderCredentialsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userCredentials = $form->getData();

            // Store encrypted credentials in DB for next time
            $encryptedCredentials = $this->encryptionService->encrypt(
                json_encode($userCredentials, JSON_THROW_ON_ERROR)
            );
            $dbUser->setProviderCredentials($encryptedCredentials);
            $this->userRepository->save($dbUser);

            return $this->completeAuthorizationWithCredentials(
                $providerConfig,
                $userCredentials,
                $dbUser->getId()
            );
        }

        return $this->render('oauth/authorize.html.twig', [
            'form' => $form,
            'auth_user_name' => $this->oauthSession->getMcpUserName(),
            'auth_user_email' => $userEmail,
            'organization' => $organization,
        ]);
    }

    /**
     * Complete OAuth authorization with org config + user credentials.
     *
     * @param array<string, mixed> $orgConfig       Organization-level config (url)
     * @param array<string, mixed> $userCredentials User-level credentials (api_key)
     */
    private function completeAuthorizationWithCredentials(
        array $orgConfig,
        array $userCredentials,
        int $userId,
    ): Response {
        $oauthParams = $this->oauthSession->getMcpOAuthParams();

        if (null === $oauthParams) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Session expired, please start authorization again'], 400);
        }

        // Generate and store authorization code with combined credentials
        $authCode = bin2hex(random_bytes(32));
        $authData = [
            'user_id' => $userId,
            'client_id' => $oauthParams['client_id'],
            'redirect_uri' => $oauthParams['redirect_uri'],
            'provider' => 'redmine',
            'org_config' => $orgConfig,
            'user_credentials' => $userCredentials,
        ];

        $this->codeStore->store($authCode, $authData);
        $this->oauthSession->clearMcpOAuthFlow();

        // Redirect back to client with authorization code
        $redirectUrl = $oauthParams['redirect_uri'].'?code='.$authCode;
        if ('' !== $oauthParams['state']) {
            $redirectUrl .= '&state='.urlencode($oauthParams['state']);
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Handle signup flow callback after authentication.
     *
     * @param array{email: string, name: string, id: string} $authUser
     */
    private function handleSignupCallback(array $authUser): Response
    {
        if ($this->userRepository->findByEmail($authUser['email'])) {
            $this->oauthSession->clearSignupFlow();
            $this->addFlash('error', 'You already have an account. Log in to access your organization, or use a different email to create a new one.');

            return $this->redirectToRoute('admin_login');
        }

        $this->oauthSession->storeSignupUser($authUser);

        return $this->redirectToRoute('admin_signup_wizard');
    }

    /**
     * Handle MCP OAuth flow callback after authentication.
     *
     * @param array{email: string, name: string, id: string} $authUser
     */
    private function handleMcpOAuthCallback(array $authUser): Response
    {
        // User will be checked in oauthCallback (must exist in DB and be approved)
        $this->oauthSession->storeMcpUser($authUser);

        return $this->redirectToRoute('oauth_callback');
    }

    /**
     * Handle admin login flow after authentication.
     */
    private function handleAdminLogin(string $email): Response
    {
        $this->oauthSession->clearAdminLogin();

        // Find user in DB
        $user = $this->userRepository->findByEmail($email);

        if (null === $user || (!$user->isOrgAdmin() && !$user->isSuperAdmin())) {
            return $this->render('admin/login_error.html.twig', [
                'error' => 'Unable to authenticate.',
            ]);
        }

        // Regenerate session ID to prevent session fixation attacks
        $session = $this->oauthSession->getSession();
        $session->migrate(true);
        $session->set('admin_user_id', $user->getId());

        return $this->redirectToRoute('admin_dashboard');
    }
}
