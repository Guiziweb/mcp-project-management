<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\McpSessionRepository;
use App\Shared\Infrastructure\Security\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/settings')]
#[IsGranted('ROLE_USER')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly McpSessionRepository $sessionRepository,
        private readonly EncryptionService $encryptionService,
    ) {
    }

    #[Route('', name: 'user_settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('api_key', PasswordType::class, [
                'required' => false,
                'attr' => ['placeholder' => $user->hasProviderCredentials() ? '••••••••' : 'Enter your API key'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $apiKey = $form->get('api_key')->getData();

            if ($apiKey) {
                $encrypted = $this->encryptionService->encrypt(
                    json_encode(['api_key' => $apiKey], JSON_THROW_ON_ERROR)
                );
                $user->setProviderCredentials($encrypted);
                $this->em->flush();

                $this->addFlash('success', 'API key updated successfully.');
            }

            return $this->redirectToRoute('user_settings');
        }

        $sessions = $this->sessionRepository->findByUser($user);

        return $this->render('admin/settings/index.html.twig', [
            'user' => $user,
            'form' => $form,
            'sessions' => $sessions,
        ]);
    }
}
