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
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Self-service organization onboarding wizard.
 *
 * Flow:
 * 1. /admin/signup -> redirect to Google OAuth
 * 2. Google callback -> verify email -> store user in session -> redirect to wizard
 * 3. /admin/signup/wizard -> FormFlow (organization -> provider -> create)
 */
final class OnboardingController extends AbstractController
{
    private const SESSION_KEY = 'signup_flow_data';

    public function __construct(
        private readonly OAuthSessionManager $oauthSession,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Entry point: redirect to social auth provider.
     */
    #[Route('/admin/signup', name: 'admin_signup', methods: ['GET'])]
    public function signup(): Response
    {
        // If already authenticated, go to wizard
        if (null !== $this->oauthSession->getSignupUser()) {
            return $this->redirectToRoute('admin_signup_wizard');
        }

        // Redirect to OAuth provider (uses default callback /oauth/callback)
        $this->oauthSession->markAsSignupFlow();
        $authUrl = $this->oauthSession->startAuth();

        return $this->redirect($authUrl);
    }

    /**
     * Wizard: organization + provider steps.
     * Called after OAuth callback redirects here with user info in session.
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

        $dataStorage = new SessionDataStorage(self::SESSION_KEY, $this->requestStack);
        $loaded = $dataStorage->load();
        $data = $loaded instanceof OrganizationSignUp ? $loaded : new OrganizationSignUp();

        $flow = $this->createForm(OrganizationSignUpFlowType::class, $data, [
            'data_storage' => $dataStorage,
        ])->handleRequest($request);

        // If finished, create org and user
        if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished()) {
            \assert($flow->getData() instanceof OrganizationSignUp);

            return $this->createOrganization($flow->getData(), $authUser, $session);
        }

        return $this->render('admin/signup/flow.html.twig', [
            'form' => $flow->getStepForm(),
        ]);
    }

    /**
     * Create organization and user, then redirect to dashboard.
     *
     * @param array{email: string, id: string, name: string} $authUser
     */
    private function createOrganization(OrganizationSignUp $data, array $authUser, SessionInterface $session): Response
    {
        // If user already exists, log them in (they authenticated via OAuth)
        $existingUser = $this->userRepository->findByEmail($authUser['email']);
        if ($existingUser) {
            $this->cleanupSession();
            $session->migrate(true);
            $session->set('admin_user_id', $existingUser->getId());
            $this->addFlash('info', 'Vous aviez déjà un compte, vous êtes maintenant connecté.');

            return $this->redirectToRoute('admin_dashboard');
        }

        $now = $this->clock->now();

        // Create organization (slug is auto-generated from name)
        // Values guaranteed non-null by form validation
        \assert(null !== $data->name && null !== $data->providerType);
        $organization = new Organization($data->name, null, $data->providerType, $now);
        $organization->setProviderUrl($data->providerUrl);
        $organization->setSize($data->size);
        $this->entityManager->persist($organization);

        // Create admin user (auto-approved since they're creating their own org)
        $user = new User($authUser['email'], $authUser['id'], $organization, $now);
        $user->setName($authUser['name']);
        $user->setRoles([User::ROLE_ORG_ADMIN]);
        $user->approve();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->cleanupSession();
        $session->migrate(true);
        $session->set('admin_user_id', $user->getId());
        $this->addFlash('success', 'Organisation créée avec succès !');

        return $this->redirectToRoute('admin_dashboard');
    }

    private function cleanupSession(): void
    {
        $dataStorage = new SessionDataStorage(self::SESSION_KEY, $this->requestStack);
        $dataStorage->clear();
        $this->oauthSession->clearSignupFlow();
    }
}
