<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Service\ToolRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/organization')]
#[IsGranted('ROLE_ORG_ADMIN')]
final class MyOrganizationController extends AbstractController
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_my_organization', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $organization = $user->getOrganization();

        $form = $this->createFormBuilder($organization)
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

            $this->addFlash('success', 'Enabled tools updated successfully.');

            return $this->redirectToRoute('admin_my_organization');
        }

        return $this->render('admin/my_organization/index.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }
}
