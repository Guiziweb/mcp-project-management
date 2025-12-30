<?php

declare(strict_types=1);

namespace App\OAuth\Application\UseCase;

use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\OAuth\Infrastructure\Security\OAuthAuthorizationCodeStore;
use App\OAuth\Infrastructure\Security\TokenService;

/**
 * Exchanges an OAuth authorization code for access and refresh tokens.
 */
final class ExchangeCodeForTokensUseCase
{
    public function __construct(
        private readonly OAuthAuthorizationCodeStore $codeStore,
        private readonly TokenService $tokenService,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @throws InvalidAuthorizationCodeException
     * @throws RedirectUriMismatchException
     */
    public function execute(string $code, string $redirectUri): TokenPair
    {
        $authData = $this->codeStore->consumeOnce($code);

        if (null === $authData) {
            throw new InvalidAuthorizationCodeException('Invalid or expired authorization code');
        }

        if ($authData['redirect_uri'] !== $redirectUri) {
            throw new RedirectUriMismatchException('Redirect URI mismatch');
        }

        $user = $this->userRepository->find($authData['user_id']);
        if (null === $user) {
            throw new InvalidAuthorizationCodeException('User not found');
        }

        $credentials = [
            'provider' => $authData['provider'],
            'org_config' => $authData['org_config'],
            'user_credentials' => $authData['user_credentials'],
        ];

        $tokens = $this->tokenService->createTokenPair($user, $credentials);

        return new TokenPair($tokens['access_token'], $tokens['refresh_token']);
    }
}
