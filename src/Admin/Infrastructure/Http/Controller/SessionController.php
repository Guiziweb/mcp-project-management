<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\McpSession;
use App\Admin\Infrastructure\Doctrine\Repository\McpSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/sessions')]
#[IsGranted('ROLE_ORG_ADMIN')]
final class SessionController extends AbstractController
{
    public function __construct(
        private readonly McpSessionRepository $sessionRepository,
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    #[Route('', name: 'admin_sessions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $qb = $this->sessionRepository->createQueryBuilder('s')
            ->orderBy('s.lastActivityAt', 'DESC');

        $sessions = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/sessions/index.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_sessions_delete', methods: ['GET'])]
    public function delete(McpSession $session): Response
    {
        $this->denyAccessUnlessGranted('DELETE', $session);

        $this->em->remove($session);
        $this->em->flush();

        $this->addFlash('success', 'Session deleted successfully.');

        return $this->redirectToRoute('admin_sessions');
    }
}
