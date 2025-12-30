<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Shared\Infrastructure\Security\GoogleAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly GoogleAuthService $googleAuth,
    ) {
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('admin/login.html.twig');
    }

    #[Route('/admin/login/redirect', name: 'admin_login_redirect', methods: ['GET'])]
    public function loginRedirect(Request $request): Response
    {
        // Set flag to indicate this is an admin login flow
        $session = $request->getSession();
        $session->set('admin_login', true);

        // Get Google OAuth URL and redirect
        $googleAuth = $this->googleAuth->getAuthorizationUrl();
        $session->set('google_oauth_state', $googleAuth['state']);

        return $this->redirect($googleAuth['url']);
    }

    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): void
    {
        // This is handled by Symfony's security system
        throw new \LogicException('This method should never be reached.');
    }
}
