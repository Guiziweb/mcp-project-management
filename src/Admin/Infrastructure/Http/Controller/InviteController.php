<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\InviteLinkRepository;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\OAuth\Infrastructure\Http\Form\ProviderCredentialsType;
use App\Shared\Infrastructure\Security\EncryptionService;
use App\Shared\Infrastructure\Security\GoogleAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Handles invite links for user onboarding.
 *
 * Flow:
 * 1. Admin generates invite link
 * 2. User clicks link → redirected to Google OAuth
 * 3. After Google auth → user enters their API key
 * 4. User account created in DB, linked to organization
 */
final class InviteController extends AbstractController
{
    public function __construct(
        private readonly InviteLinkRepository $inviteLinkRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly GoogleAuthService $googleAuth,
        private readonly EncryptionService $encryptionService,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Landing page for invite link.
     * Redirects to Google OAuth.
     */
    #[Route('/join/{token}', name: 'invite_join', methods: ['GET'])]
    public function join(string $token, Request $request): Response
    {
        try {
            $uuid = Uuid::fromString($token);
        } catch (\InvalidArgumentException) {
            return $this->render('invite/invalid.html.twig', [
                'error' => 'Invalid invite link format.',
            ]);
        }

        $inviteLink = $this->inviteLinkRepository->findValidByToken($uuid);

        if (null === $inviteLink) {
            return $this->render('invite/invalid.html.twig', [
                'error' => 'This invite link is invalid or has expired.',
            ]);
        }

        // Store invite token in session and redirect to Google OAuth
        $session = $request->getSession();
        $session->set('invite_token', $token);

        // Use invite-specific callback URL
        $callbackUrl = $this->generateUrl('invite_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $googleAuth = $this->googleAuth->getAuthorizationUrl($callbackUrl);
        $session->set('google_oauth_state', $googleAuth['state']);

        return $this->redirect($googleAuth['url']);
    }

    /**
     * Google OAuth callback for invite flow.
     * Creates user account after successful authentication.
     */
    #[Route('/join/callback', name: 'invite_callback', methods: ['GET', 'POST'], priority: 10)]
    public function callback(Request $request): Response
    {
        $session = $request->getSession();
        $inviteToken = $session->get('invite_token');

        if (!$inviteToken) {
            return $this->render('invite/invalid.html.twig', [
                'error' => 'Session expired. Please use your invite link again.',
            ]);
        }

        // Handle Google OAuth callback
        if ($request->isMethod('GET') && $request->query->has('code')) {
            $code = $request->query->get('code');
            $state = $request->query->get('state');
            $expectedState = $session->get('google_oauth_state');

            if (!$code || !is_string($code)) {
                return $this->render('invite/invalid.html.twig', [
                    'error' => 'Google authentication failed.',
                ]);
            }

            try {
                // Use the same callback URL as in join()
                $callbackUrl = $this->generateUrl('invite_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $googleUser = $this->googleAuth->handleCallback($code, (string) $state, $expectedState, $callbackUrl);
                $session->set('google_user_email', $googleUser['email']);
                $session->set('google_user_id', $googleUser['id']);
                $session->set('google_user_name', $googleUser['name']);
            } catch (\RuntimeException $e) {
                return $this->render('invite/invalid.html.twig', [
                    'error' => 'Google authentication failed: '.$e->getMessage(),
                ]);
            }
        }

        // Check if user already exists
        $userEmail = $session->get('google_user_email');
        $googleId = $session->get('google_user_id');

        if (!$userEmail || !$googleId) {
            return $this->render('invite/invalid.html.twig', [
                'error' => 'Session expired. Please use your invite link again.',
            ]);
        }

        $existingUser = $this->userRepository->findByEmail($userEmail);
        if ($existingUser) {
            // User already exists, just redirect to success
            $session->remove('invite_token');
            $session->remove('google_oauth_state');
            $session->remove('google_user_email');
            $session->remove('google_user_id');
            $session->remove('google_user_name');

            return $this->render('invite/success.html.twig', [
                'user' => $existingUser,
                'already_exists' => true,
            ]);
        }

        // Validate invite link again
        $inviteLink = $this->inviteLinkRepository->findValidByToken(Uuid::fromString($inviteToken));
        if (null === $inviteLink) {
            return $this->render('invite/invalid.html.twig', [
                'error' => 'This invite link is no longer valid.',
            ]);
        }

        $organization = $inviteLink->getOrganization();

        // Show form to enter credentials
        $form = $this->createForm(ProviderCredentialsType::class, null, [
            'provider_type' => $organization->getProviderType(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userCredentials = $form->getData();

            // Create user
            $user = new User($userEmail, $googleId, $organization, $this->clock->now());

            // Encrypt and store credentials as JSON
            $encryptedCredentials = $this->encryptionService->encrypt(
                json_encode($userCredentials, JSON_THROW_ON_ERROR)
            );
            $user->setProviderCredentials($encryptedCredentials);

            // Use invite link and persist changes
            $inviteLink->use();

            $this->em->persist($user);
            $this->em->persist($inviteLink);
            $this->em->flush();

            // Clear session
            $session->remove('invite_token');
            $session->remove('google_oauth_state');
            $session->remove('google_user_email');
            $session->remove('google_user_id');
            $session->remove('google_user_name');

            return $this->render('invite/success.html.twig', [
                'user' => $user,
                'already_exists' => false,
            ]);
        }

        return $this->render('invite/credentials.html.twig', [
            'form' => $form,
            'organization' => $organization,
            'google_user_name' => $session->get('google_user_name'),
            'google_user_email' => $userEmail,
        ]);
    }
}
