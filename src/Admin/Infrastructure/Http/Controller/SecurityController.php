<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Shared\Infrastructure\Security\OAuthSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly OAuthSessionManager $oauthSession,
    ) {
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('admin/login.html.twig');
    }

    #[Route('/admin/login/redirect', name: 'admin_login_redirect', methods: ['GET'])]
    public function loginRedirect(): Response
    {
        $this->oauthSession->markAsAdminLogin();
        $authUrl = $this->oauthSession->startAuth();

        return $this->redirect($authUrl);
    }

    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): void
    {
        // This is handled by Symfony's security system
        throw new \LogicException('This method should never be reached.');
    }
}
