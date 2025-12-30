<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\Admin\Infrastructure\Service\ToolRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ORG_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly ToolRegistry $toolRegistry,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('q', '');

        $qb = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.lastSeenAt', 'DESC');

        if ($search) {
            $qb->andWhere('u.email LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('admin/users/index.html.twig', [
            'users' => $pagination,
            'search' => $search,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $user);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Build role choices based on current user
        $roleChoices = [
            'User' => User::ROLE_USER,
            'Org Admin' => User::ROLE_ORG_ADMIN,
        ];
        if ($currentUser->isSuperAdmin()) {
            $roleChoices['Super Admin'] = User::ROLE_SUPER_ADMIN;
        }

        $form = $this->createFormBuilder($user)
            ->add('roles', ChoiceType::class, [
                'choices' => $roleChoices,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('enabledTools', ChoiceType::class, [
                'choices' => $this->toolRegistry->getToolChoices(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['GET'])]
    public function delete(User $user): Response
    {
        $this->denyAccessUnlessGranted('DELETE', $user);

        $this->em->remove($user);
        $this->em->flush();

        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/{id}/approve', name: 'admin_users_approve', methods: ['POST'])]
    public function approve(User $user): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $user);

        if ($user->isPending()) {
            $user->approve();
            $this->em->flush();
            $this->addFlash('success', sprintf('User %s approved.', $user->getEmail()));
        }

        return $this->redirectToRoute('admin_users');
    }
}
