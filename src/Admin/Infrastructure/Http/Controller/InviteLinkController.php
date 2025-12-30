<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\InviteLink;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\InviteLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/invites')]
#[IsGranted('ROLE_ORG_ADMIN')]
final class InviteLinkController extends AbstractController
{
    public function __construct(
        private readonly InviteLinkRepository $inviteRepository,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'admin_invites', methods: ['GET'])]
    public function index(): Response
    {
        $invites = $this->inviteRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/invites/index.html.twig', [
            'invites' => $invites,
        ]);
    }

    #[Route('/create', name: 'admin_invites_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('label', TextType::class, [
                'required' => false,
                'label' => 'Label (optional)',
                'attr' => ['placeholder' => 'Ex: Backend Team'],
            ])
            ->add('expiresAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Expiration date',
                'data' => new \DateTimeImmutable('+7 days'),
            ])
            ->add('maxUses', IntegerType::class, [
                'required' => false,
                'label' => 'Maximum number of uses',
                'attr' => ['placeholder' => 'Empty = unlimited', 'min' => 1],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $invite = new InviteLink(
                $currentUser->getOrganization(),
                $currentUser,
                $data['expiresAt'],
                $this->clock->now()
            );

            if ($data['label']) {
                $invite->setLabel($data['label']);
            }
            if ($data['maxUses']) {
                $invite->setMaxUses($data['maxUses']);
            }

            $this->em->persist($invite);
            $this->em->flush();

            $this->addFlash('success', 'Invite link created successfully.');

            return $this->redirectToRoute('admin_invites');
        }

        return $this->render('admin/invites/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{token}/delete', name: 'admin_invites_delete', methods: ['GET'])]
    public function delete(#[MapEntity(mapping: ['token' => 'token'])] InviteLink $invite): Response
    {
        $this->denyAccessUnlessGranted('DELETE', $invite);

        $this->em->remove($invite);
        $this->em->flush();

        $this->addFlash('success', 'Invite link deleted successfully.');

        return $this->redirectToRoute('admin_invites');
    }
}
