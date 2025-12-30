<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\OAuth\Infrastructure\Http\Form\ProviderCredentialsType;
use App\OAuth\Infrastructure\Security\OAuthAuthorizationCodeStore;
use App\OAuth\Infrastructure\Security\RedirectUriValidator;
use App\Shared\Infrastructure\Security\EncryptionService;
use App\Shared\Infrastructure\Security\GoogleAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth 2.0 Authorization endpoint.
 * Handles user authentication via Google and credential collection.
 */
final class AuthorizationController extends AbstractController
{
    public function __construct(
        private readonly GoogleAuthService $googleAuth,
        private readonly UserRepository $userRepository,
        private readonly EncryptionService $encryptionService,
        private readonly OAuthAuthorizationCodeStore $codeStore,
        private readonly RedirectUriValidator $redirectUriValidator,
    ) {
    }

    /**
     * OAuth authorization endpoint.
     * Redirects to Google for authentication.
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

        $session = $request->getSession();
        $session->set('oauth_client_id', $clientId);
        $session->set('oauth_redirect_uri', $redirectUri);
        $session->set('oauth_state', $state);

        $googleAuth = $this->googleAuth->getAuthorizationUrl();
        $session->set('google_oauth_state', $googleAuth['state']);

        return $this->redirect($googleAuth['url']);
    }

    /**
     * Google OAuth callback endpoint.
     * Receives the user from Google, then shows form to enter provider credentials.
     * Also handles admin login flow when admin_login session flag is set.
     */
    #[Route('/oauth/google-callback', name: 'oauth_google_callback', methods: ['GET', 'POST'])]
    public function googleCallback(Request $request): Response
    {
        $session = $request->getSession();

        // Handle GET: Google redirects back with authorization code
        if ($request->isMethod('GET') && $request->query->has('code')) {
            $code = $request->query->get('code');
            $state = $request->query->get('state');
            $expectedState = $session->get('google_oauth_state');

            if (!$code) {
                return new JsonResponse(['error' => 'access_denied', 'error_description' => 'User denied access or Google error'], 400);
            }

            if (!$expectedState) {
                return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Session expired, please start authorization again'], 400);
            }

            try {
                $googleUser = $this->googleAuth->handleCallback($code, (string) $state, $expectedState);

                // Dispatch to appropriate handler based on session flags
                if ($session->get('signup_flow')) {
                    return $this->handleSignupCallback($session, $googleUser);
                }

                if ($session->get('admin_login')) {
                    return $this->handleAdminLogin($session, $googleUser['email']);
                }

                // Default: MCP OAuth flow
                return $this->handleMcpOAuthCallback($session, $googleUser);
            } catch (\RuntimeException $e) {
                return new JsonResponse(['error' => 'server_error', 'error_description' => $e->getMessage()], 500);
            }
        }

        // Check session validity
        $userEmail = $session->get('google_user_email');
        if (!$userEmail) {
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
        $providerType = $organization->getProviderType();
        $providerConfig = $organization->getProviderConfig();

        // If user already has credentials, auto-authorize
        if ($dbUser->hasProviderCredentials()) {
            $decryptedCredentials = json_decode(
                $this->encryptionService->decrypt($dbUser->getProviderCredentials()),
                true
            );

            return $this->completeAuthorizationWithCredentials(
                $session,
                $providerType,
                $providerConfig,
                $decryptedCredentials,
                $dbUser->getId()
            );
        }

        // User needs to enter their credentials - show form with only user fields
        $form = $this->createForm(ProviderCredentialsType::class, null, [
            'provider_type' => $providerType,
        ]);
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
                $session,
                $providerType,
                $providerConfig,
                $userCredentials,
                $dbUser->getId()
            );
        }

        return $this->render('oauth/authorize.html.twig', [
            'form' => $form,
            'google_user_name' => $session->get('google_user_name'),
            'google_user_email' => $userEmail,
            'organization' => $organization,
        ]);
    }

    /**
     * Complete OAuth authorization with org config + user credentials.
     *
     * @param array<string, mixed> $orgConfig       Organization-level config (url, etc.)
     * @param array<string, mixed> $userCredentials User-level credentials (api_key, email, etc.)
     */
    private function completeAuthorizationWithCredentials(
        SessionInterface $session,
        string $providerType,
        array $orgConfig,
        array $userCredentials,
        int $userId,
    ): Response {
        $clientId = $session->get('oauth_client_id');
        $redirectUri = $session->get('oauth_redirect_uri');
        $state = $session->get('oauth_state');

        if (!\is_string($redirectUri) || '' === $redirectUri) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Session expired, please start authorization again'], 400);
        }

        // Generate and store authorization code with combined credentials
        $authCode = bin2hex(random_bytes(32));
        $authData = [
            'user_id' => $userId,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'provider' => $providerType,
            'org_config' => $orgConfig,
            'user_credentials' => $userCredentials,
        ];

        $this->codeStore->store($authCode, $authData);

        // Clear session
        $session->remove('oauth_client_id');
        $session->remove('oauth_redirect_uri');
        $session->remove('oauth_state');
        $session->remove('google_oauth_state');
        $session->remove('google_user_email');
        $session->remove('google_user_name');

        // Redirect back to client with authorization code
        $redirectUrl = $redirectUri.'?code='.$authCode;
        if ($state) {
            $redirectUrl .= '&state='.urlencode($state);
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Handle signup flow callback after Google authentication.
     *
     * @param array{email: string, name: string, id: string} $googleUser
     */
    private function handleSignupCallback(SessionInterface $session, array $googleUser): Response
    {
        $session->remove('signup_flow');
        $session->remove('google_oauth_state');

        if ($this->userRepository->findByEmail($googleUser['email'])) {
            $this->addFlash('error', 'Un compte avec cet email existe déjà. Connectez-vous plutôt.');

            return $this->redirectToRoute('admin_login');
        }

        $session->set('signup_google_user', [
            'email' => $googleUser['email'],
            'name' => $googleUser['name'],
            'id' => $googleUser['id'],
        ]);

        return $this->redirectToRoute('admin_signup_wizard');
    }

    /**
     * Handle MCP OAuth flow callback after Google authentication.
     *
     * @param array{email: string, name: string, id: string} $googleUser
     */
    private function handleMcpOAuthCallback(SessionInterface $session, array $googleUser): Response
    {
        // User will be checked in googleCallback (must exist in DB and be approved)
        $session->set('google_user_email', $googleUser['email']);
        $session->set('google_user_name', $googleUser['name']);

        return $this->redirectToRoute('oauth_google_callback');
    }

    /**
     * Handle admin login flow after Google authentication.
     */
    private function handleAdminLogin(SessionInterface $session, string $email): Response
    {
        // Clean up admin login flag
        $session->remove('admin_login');
        $session->remove('google_oauth_state');

        // Find user in DB
        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            return $this->render('admin/login_error.html.twig', [
                'error' => 'User not found. Please contact your administrator.',
            ]);
        }

        if (!$user->isOrgAdmin() && !$user->isSuperAdmin()) {
            return $this->render('admin/login_error.html.twig', [
                'error' => 'You do not have admin access.',
            ]);
        }

        // Store user in session for admin authentication
        $session->set('admin_user_id', $user->getId());

        return $this->redirectToRoute('admin_dashboard');
    }
}
