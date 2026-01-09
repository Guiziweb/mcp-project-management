<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\Admin\Infrastructure\Dto\OrganizationSignUp;
use App\Admin\Infrastructure\Http\Form\Signup\OrganizationSignUpFlowType;
use App\Shared\Infrastructure\Security\OAuthSessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Self-service organization onboarding.
 *
 * Flow:
 * 1. /admin/signup -> redirect to Google OAuth
 * 2. Google callback -> verify email -> store user in session -> redirect to form
 * 3. /admin/signup/wizard -> Single form (organization name, size, Redmine URL)
 */
final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly OAuthSessionManager $oauthSession,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Entry point: redirect to social auth provider.
     */
    #[Route('/admin/signup', name: 'admin_signup', methods: ['GET'])]
    public function signup(): Response
    {
        // If already authenticated, go to form
        if (null !== $this->oauthSession->getSignupUser()) {
            return $this->redirectToRoute('admin_signup_wizard');
        }

        // Redirect to OAuth provider
        $this->oauthSession->markAsSignupFlow();
        $authUrl = $this->oauthSession->startAuth();

        return $this->redirect($authUrl);
    }

    /**
     * Organization signup form.
     */
    #[Route('/admin/signup/wizard', name: 'admin_signup_wizard', methods: ['GET', 'POST'])]
    public function wizard(Request $request): Response
    {
        $session = $request->getSession();

        // Must be authenticated first
        $authUser = $this->oauthSession->getSignupUser();
        if (null === $authUser) {
            return $this->redirectToRoute('admin_signup');
        }

        $data = new OrganizationSignUp();
        $form = $this->createForm(OrganizationSignUpFlowType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->createOrganization($data, $authUser, $session);
        }

        return $this->render('admin/signup/form.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Create organization and user, then redirect to dashboard.
     *
     * @param array{email: string, id: string, name: string} $authUser
     */
    private function createOrganization(OrganizationSignUp $data, array $authUser, SessionInterface $session): Response
    {
        // If user already exists, log them in
        $existingUser = $this->userRepository->findByEmail($authUser['email']);
        if ($existingUser) {
            $this->oauthSession->clearSignupFlow();
            $session->migrate(true);
            $session->set('admin_user_id', $existingUser->getId());
            $this->addFlash('info', 'Vous aviez déjà un compte, vous êtes maintenant connecté.');

            return $this->redirectToRoute('admin_dashboard');
        }

        $now = $this->clock->now();

        // Create organization with Redmine
        \assert(null !== $data->name && null !== $data->redmineUrl);
        $organization = new Organization($data->name, null, $now);
        $organization->setProviderUrl($data->redmineUrl);
        $organization->setSize($data->size);
        $this->entityManager->persist($organization);

        // Create admin user
        $user = new User($authUser['email'], $authUser['id'], $organization, $now);
        $user->setName($authUser['name']);
        $user->setRoles([User::ROLE_ORG_ADMIN]);
        $user->approve();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->oauthSession->clearSignupFlow();
        $session->migrate(true);
        $session->set('admin_user_id', $user->getId());
        $this->addFlash('success', 'Organisation créée avec succès !');

        return $this->redirectToRoute('admin_dashboard');
    }
}
