<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\Admin\Infrastructure\Dto\OrganizationSignUp;
use App\Admin\Infrastructure\Http\Form\Signup\OrganizationSignUpFlowType;
use App\Shared\Infrastructure\Security\GoogleAuthService;
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
    private const GOOGLE_USER_KEY = 'signup_google_user';

    public function __construct(
        private readonly GoogleAuthService $googleAuth,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Entry point: redirect to Google OAuth.
     */
    #[Route('/admin/signup', name: 'admin_signup', methods: ['GET'])]
    public function signup(Request $request): Response
    {
        $session = $request->getSession();

        // If already authenticated with Google, go to wizard
        if ($session->has(self::GOOGLE_USER_KEY)) {
            return $this->redirectToRoute('admin_signup_wizard');
        }

        // Redirect to Google OAuth (uses default callback /oauth/google-callback)
        $googleAuth = $this->googleAuth->getAuthorizationUrl();
        $session->set('google_oauth_state', $googleAuth['state']);
        $session->set('signup_flow', true);

        return $this->redirect($googleAuth['url']);
    }

    /**
     * Wizard: organization + provider steps.
     * Called after Google OAuth callback redirects here with user info in session.
     */
    #[Route('/admin/signup/wizard', name: 'admin_signup_wizard', methods: ['GET', 'POST'])]
    public function wizard(Request $request): Response
    {
        $session = $request->getSession();

        // Must be authenticated with Google first
        $googleUser = $session->get(self::GOOGLE_USER_KEY);
        if (!$googleUser) {
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
            \assert(\is_array($googleUser) && isset($googleUser['email'], $googleUser['id'], $googleUser['name']));

            return $this->createOrganization($flow->getData(), $googleUser, $session);
        }

        return $this->render('admin/signup/flow.html.twig', [
            'form' => $flow->getStepForm(),
        ]);
    }

    /**
     * Create organization and user, then redirect to dashboard.
     *
     * @param array{email: string, id: string, name: string} $googleUser
     */
    private function createOrganization(OrganizationSignUp $data, array $googleUser, SessionInterface $session): Response
    {
        // If user already exists, log them in (they authenticated via Google)
        $existingUser = $this->userRepository->findByEmail($googleUser['email']);
        if ($existingUser) {
            $this->cleanupSession();
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
        $user = new User($googleUser['email'], $googleUser['id'], $organization, $now);
        $user->setName($googleUser['name']);
        $user->setRoles([User::ROLE_ORG_ADMIN]);
        $user->approve();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->cleanupSession();
        $session->set('admin_user_id', $user->getId());
        $this->addFlash('success', 'Organisation créée avec succès !');

        return $this->redirectToRoute('admin_dashboard');
    }

    private function cleanupSession(): void
    {
        $session = $this->requestStack->getSession();
        $dataStorage = new SessionDataStorage(self::SESSION_KEY, $this->requestStack);
        $dataStorage->clear();
        $session->remove(self::GOOGLE_USER_KEY);
        $session->remove('signup_flow');
    }
}
