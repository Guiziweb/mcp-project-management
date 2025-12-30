<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\McpSessionRepository;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORG_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly McpSessionRepository $sessionRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function index(): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $users = $this->userRepository->findAll();
        $sessions = $this->sessionRepository->findAll();

        // Count active sessions (last 5 min)
        $activeCount = 0;
        $threshold = $this->clock->now()->modify('-5 minutes');
        foreach ($sessions as $session) {
            if ($session->getLastActivityAt() >= $threshold) {
                ++$activeCount;
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'organization' => $currentUser->getOrganization(),
            'userCount' => count($users),
            'sessionCount' => count($sessions),
            'activeCount' => $activeCount,
        ]);
    }
}
