<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security;

use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AdminAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only support admin routes
        return str_starts_with($request->getPathInfo(), '/admin');
    }

    public function authenticate(Request $request): Passport
    {
        $session = $request->getSession();
        $userId = $session->get('admin_user_id');

        if (null === $userId) {
            throw new CustomUserMessageAuthenticationException('Please log in to access the admin area.');
        }

        return new SelfValidatingPassport(
            new UserBadge((string) $userId, function (string $identifier) {
                $user = $this->userRepository->find((int) $identifier);

                if (null === $user) {
                    throw new CustomUserMessageAuthenticationException('User not found.');
                }

                if (!$user->isOrgAdmin() && !$user->isSuperAdmin()) {
                    throw new CustomUserMessageAuthenticationException('You do not have admin access.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue to the requested page
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->remove('admin_user_id');

        return new RedirectResponse($this->urlGenerator->generate('admin_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('admin_login'));
    }
}
