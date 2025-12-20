<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ProviderCredentialsType;
use App\Infrastructure\Security\GoogleAuthService;
use App\Infrastructure\Security\JwtTokenValidator;
use App\Infrastructure\Security\OAuthAuthorizationCodeStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth 2.1 Authorization Server for MCP Redmine.
 *
 * Stateless architecture: credentials are embedded in JWT tokens.
 * No database storage required.
 */
final class OAuthController extends AbstractController
{
    public function __construct(
        private readonly JwtTokenValidator $tokenValidator,
        private readonly OAuthAuthorizationCodeStore $codeStore,
        private readonly GoogleAuthService $googleAuth,
    ) {
    }

    /**
     * RFC 9728: OAuth 2.0 Protected Resource Metadata.
     * Tells clients where the authorization server is.
     */
    #[Route('/.well-known/oauth-protected-resource', name: 'oauth_metadata', methods: ['GET'])]
    public function metadata(Request $request): JsonResponse
    {
        $baseUrl = $this->getBaseUrl($request);

        return new JsonResponse([
            'resource' => $baseUrl.'/mcp',
            'authorization_servers' => [$baseUrl],
            'bearer_methods_supported' => ['header'],
            'resource_signing_alg_values_supported' => ['HS256'],
        ]);
    }

    /**
     * RFC 8414: OAuth 2.0 Authorization Server Metadata.
     * Describes the OAuth endpoints and capabilities.
     */
    #[Route('/.well-known/oauth-authorization-server', name: 'oauth_authorization_server_metadata', methods: ['GET'])]
    public function authorizationServerMetadata(Request $request): JsonResponse
    {
        $baseUrl = $this->getBaseUrl($request);

        return new JsonResponse([
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl.'/oauth/authorize',
            'token_endpoint' => $baseUrl.'/oauth/token',
            'registration_endpoint' => $baseUrl.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'code_challenge_methods_supported' => ['plain', 'S256'],
        ]);
    }

    /**
     * RFC 7591: OAuth 2.0 Dynamic Client Registration.
     * Allows clients to register themselves automatically.
     */
    #[Route('/oauth/register', name: 'oauth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $clientId = 'mcp-'.bin2hex(random_bytes(16));
        $clientSecret = bin2hex(random_bytes(32));

        return new JsonResponse([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'client_id_issued_at' => time(),
            'client_secret_expires_at' => 0,
            'redirect_uris' => $data['redirect_uris'] ?? [],
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], 201);
    }

    /**
     * OAuth authorization endpoint.
     * Redirects to Google for authentication, then shows Redmine credential form.
     */
    #[Route('/oauth/authorize', name: 'oauth_authorize', methods: ['GET'])]
    public function authorize(Request $request): Response
    {
        $clientId = $request->query->get('client_id');
        $redirectUri = $request->query->get('redirect_uri');
        $state = $request->query->get('state');

        if (!$clientId || !$redirectUri) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Missing client_id or redirect_uri'], 400);
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

            if (!$code || !is_string($code)) {
                return new JsonResponse(['error' => 'access_denied', 'error_description' => 'User denied access or Google error'], 400);
            }

            try {
                $googleUser = $this->googleAuth->handleCallback($code, (string) $state, $expectedState);

                if (!$this->isEmailAuthorized($googleUser['email'])) {
                    return new JsonResponse([
                        'error' => 'access_denied',
                        'error_description' => sprintf(
                            'Email "%s" is not authorized to access this application. Please contact your administrator.',
                            $googleUser['email']
                        ),
                    ], 403);
                }

                $session->set('google_user_email', $googleUser['email']);
                $session->set('google_user_name', $googleUser['name']);
            } catch (\RuntimeException $e) {
                return new JsonResponse(['error' => 'server_error', 'error_description' => $e->getMessage()], 500);
            }
        }

        // Check session validity
        $userEmail = $session->get('google_user_email');
        if (!$userEmail) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Session expired, please start authorization again'], 400);
        }

        // Create and handle form
        $form = $this->createForm(ProviderCredentialsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Store credentials in session for authorization code exchange
            $session->set('provider', $data['provider']);
            $session->set('provider_url', rtrim($data['provider_url'], '/'));
            $session->set('provider_api_key', $data['provider_api_key']);
            if (!empty($data['provider_email'])) {
                $session->set('provider_email', $data['provider_email']);
            }

            return $this->completeAuthorization($session);
        }

        return $this->render('oauth/authorize.html.twig', [
            'form' => $form,
            'google_user_name' => $session->get('google_user_name'),
            'google_user_email' => $userEmail,
        ]);
    }

    /**
     * Complete OAuth authorization by generating auth code and redirecting to client.
     */
    private function completeAuthorization(\Symfony\Component\HttpFoundation\Session\SessionInterface $session): Response
    {
        $clientId = $session->get('oauth_client_id');
        $redirectUri = $session->get('oauth_redirect_uri');
        $state = $session->get('oauth_state');
        $userEmail = $session->get('google_user_email');

        // Generate and store authorization code with credentials
        $authCode = bin2hex(random_bytes(32));
        $authData = [
            'user_id' => $userEmail,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'provider' => $session->get('provider', 'redmine'),
            'provider_url' => $session->get('provider_url'),
            'provider_api_key' => $session->get('provider_api_key'),
        ];

        if ($session->has('provider_email')) {
            $authData['provider_email'] = $session->get('provider_email');
        }

        $this->codeStore->store($authCode, $authData);

        // Clear session
        $session->remove('oauth_client_id');
        $session->remove('oauth_redirect_uri');
        $session->remove('oauth_state');
        $session->remove('google_oauth_state');
        $session->remove('google_user_email');
        $session->remove('google_user_name');
        $session->remove('provider');
        $session->remove('provider_url');
        $session->remove('provider_api_key');
        $session->remove('provider_email');

        // Redirect back to client with authorization code
        $redirectUrl = $redirectUri.'?code='.$authCode;
        if ($state) {
            $redirectUrl .= '&state='.urlencode($state);
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * OAuth token endpoint.
     * Exchanges authorization code for JWT access token + refresh token.
     */
    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        $grantType = $request->request->get('grant_type');

        if ('authorization_code' === $grantType) {
            return $this->handleAuthorizationCodeGrant($request);
        }

        if ('refresh_token' === $grantType) {
            return $this->handleRefreshTokenGrant($request);
        }

        return new JsonResponse(['error' => 'unsupported_grant_type'], 400);
    }

    /**
     * Handle authorization_code grant type.
     */
    private function handleAuthorizationCodeGrant(Request $request): JsonResponse
    {
        $code = $request->request->get('code');
        $redirectUri = $request->request->get('redirect_uri');

        if (!is_string($code)) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Missing code parameter'], 400);
        }

        $authData = $this->codeStore->consumeOnce($code);

        if (null === $authData) {
            return new JsonResponse(['error' => 'invalid_grant', 'error_description' => 'Invalid or expired authorization code'], 400);
        }

        if ($authData['redirect_uri'] !== $redirectUri) {
            return new JsonResponse(['error' => 'invalid_grant', 'error_description' => 'Redirect URI mismatch'], 400);
        }

        // Build credentials array
        $credentials = [
            'provider' => $authData['provider'],
            'url' => $authData['provider_url'],
            'key' => $authData['provider_api_key'],
        ];

        if (!empty($authData['provider_email'])) {
            $credentials['email'] = $authData['provider_email'];
        }

        // Generate tokens with embedded credentials
        $accessToken = $this->tokenValidator->createAccessToken(
            userId: $authData['user_id'],
            credentials: $credentials,
        );

        $refreshToken = $this->tokenValidator->createRefreshToken(
            userId: $authData['user_id'],
            credentials: $credentials,
        );

        return new JsonResponse([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400, // 24 hours
        ]);
    }

    /**
     * Handle refresh_token grant type.
     */
    private function handleRefreshTokenGrant(Request $request): JsonResponse
    {
        $refreshToken = $request->request->get('refresh_token');

        if (!is_string($refreshToken)) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Missing refresh_token parameter'], 400);
        }

        try {
            // Validate refresh token
            if (!$this->tokenValidator->isRefreshToken($refreshToken)) {
                return new JsonResponse(['error' => 'invalid_grant', 'error_description' => 'Token is not a refresh token'], 400);
            }

            // Extract user info and credentials from refresh token
            $payload = $this->tokenValidator->decodeToken($refreshToken);
            $credentials = $this->tokenValidator->extractCredentials($refreshToken);

            // Generate new tokens (credentials already in correct format from extractCredentials)
            $newAccessToken = $this->tokenValidator->createAccessToken(
                userId: (string) $payload->sub,
                credentials: $credentials,
                role: $payload->role ?? 'user',
                isBot: $payload->is_bot ?? false,
            );

            $newRefreshToken = $this->tokenValidator->createRefreshToken(
                userId: (string) $payload->sub,
                credentials: $credentials,
                role: $payload->role ?? 'user',
                isBot: $payload->is_bot ?? false,
            );

            return new JsonResponse([
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'invalid_grant', 'error_description' => $e->getMessage()], 400);
        }
    }

    /**
     * Check if an email is authorized to access this application.
     */
    private function isEmailAuthorized(string $email): bool
    {
        $allowedDomains = $_ENV['ALLOWED_EMAIL_DOMAINS'] ?? '';
        if ('' !== $allowedDomains) {
            $domains = array_map('trim', explode(',', $allowedDomains));
            foreach ($domains as $domain) {
                if ('' !== $domain && str_ends_with($email, '@'.$domain)) {
                    return true;
                }
            }
        }

        $allowedEmails = $_ENV['ALLOWED_EMAILS'] ?? '';
        if ('' !== $allowedEmails) {
            $emails = array_map('trim', explode(',', $allowedEmails));
            if (in_array($email, $emails, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get base URL from request, respecting proxy headers.
     */
    private function getBaseUrl(Request $request): string
    {
        return $request->headers->get('X-Forwarded-Proto')
            ? $request->headers->get('X-Forwarded-Proto').'://'.$request->getHost()
            : $request->getSchemeAndHttpHost();
    }
}
