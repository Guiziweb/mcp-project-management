<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Model\UserCredential;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates users via JWT tokens in the Authorization header.
 *
 * Stateless architecture: credentials are extracted directly from the JWT,
 * no database lookup required.
 */
final class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtTokenValidator $tokenValidator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);

        try {
            // Decode the full token payload
            $payload = $this->tokenValidator->decodeToken($token);
            $userId = (string) $payload->sub;

            // Extract credentials from token
            $credentials = $this->tokenValidator->extractCredentials($token);

            // Create UserCredential from token data
            $credential = new UserCredential(
                userId: $userId,
                redmineUrl: $credentials['url'],
                redmineApiKey: $credentials['key'],
                role: $payload->role ?? 'user',
                isBot: $payload->is_bot ?? false,
            );

            // Create User directly with a custom loader (no database needed)
            return new SelfValidatingPassport(
                new UserBadge($userId, fn () => new User($credential))
            );
        } catch (\RuntimeException $e) {
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $scheme = $request->headers->get('X-Forwarded-Proto', $request->getScheme());
        $host = $request->getHost();
        $baseUrl = $scheme.'://'.$host;

        return new JsonResponse(
            ['error' => 'unauthorized', 'message' => $exception->getMessage()],
            401,
            [
                'WWW-Authenticate' => sprintf(
                    'Bearer realm="MCP Redmine", resource_metadata="%s/.well-known/oauth-protected-resource"',
                    $baseUrl
                ),
            ]
        );
    }
}
