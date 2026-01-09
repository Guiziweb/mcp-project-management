<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Repository\OrganizationRepository;
use App\Admin\Infrastructure\Service\ToolRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/organizations')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly OrganizationRepository $orgRepository,
        private readonly EntityManagerInterface $em,
        private readonly ToolRegistry $toolRegistry,
    ) {
    }

    #[Route('', name: 'admin_organizations', methods: ['GET'])]
    public function index(): Response
    {
        $organizations = $this->orgRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/organizations/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_organizations_edit', methods: ['GET', 'POST'])]
    public function edit(Organization $org, Request $request): Response
    {
        $form = $this->createFormBuilder($org)
            ->add('name', TextType::class)
            ->add('slug', TextType::class)
            ->add('providerUrl', UrlType::class, [
                'label' => 'Redmine URL',
                'required' => false,
                'default_protocol' => null,
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

            $this->addFlash('success', 'Organization updated successfully.');

            return $this->redirectToRoute('admin_organizations');
        }

        return $this->render('admin/organizations/edit.html.twig', [
            'organization' => $org,
            'form' => $form,
        ]);
    }
}
