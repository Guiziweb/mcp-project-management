<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\InviteLinkRepository;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\OAuth\Infrastructure\Http\Form\ProviderCredentialsType;
use App\Shared\Infrastructure\Security\EncryptionService;
use App\Shared\Infrastructure\Security\OAuthSessionManager;
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
        private readonly OAuthSessionManager $oauthSession,
        private readonly EncryptionService $encryptionService,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Landing page for invite link.
     * Redirects to OAuth provider.
     */
    #[Route('/join/{token}', name: 'invite_join', methods: ['GET'])]
    public function join(string $token): Response
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

        // Store invite token and redirect to OAuth provider
        $this->oauthSession->storeInviteToken($token);
        $callbackUrl = $this->generateUrl('invite_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $authUrl = $this->oauthSession->startAuth($callbackUrl);

        return $this->redirect($authUrl);
    }

    /**
     * OAuth callback for invite flow.
     * Creates user account after successful authentication.
     */
    #[Route('/join/callback', name: 'invite_callback', methods: ['GET', 'POST'], priority: 10)]
    public function callback(Request $request): Response
    {
        $inviteToken = $this->oauthSession->getInviteToken();

        if (null === $inviteToken) {
            return $this->render('invite/invalid.html.twig', [
                'error' => 'Session expired. Please use your invite link again.',
            ]);
        }

        // Handle OAuth callback
        if ($request->isMethod('GET') && $request->query->has('code')) {
            $code = $request->query->getString('code');
            $state = $request->query->getString('state');

            if ('' === $code) {
                return $this->render('invite/invalid.html.twig', [
                    'error' => 'Authentication failed.',
                ]);
            }

            try {
                $callbackUrl = $this->generateUrl('invite_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $authUser = $this->oauthSession->handleCallback($code, $state, $callbackUrl);
                $this->oauthSession->storeInviteUser($authUser);
            } catch (\RuntimeException $e) {
                return $this->render('invite/invalid.html.twig', [
                    'error' => 'Authentication failed: '.$e->getMessage(),
                ]);
            }
        }

        // Get authenticated user
        $authUser = $this->oauthSession->getInviteUser();
        if (null === $authUser) {
            return $this->render('invite/invalid.html.twig', [
                'error' => 'Session expired. Please use your invite link again.',
            ]);
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($authUser['email']);
        if ($existingUser) {
            $this->oauthSession->clearInviteFlow();

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

        // Show form to enter Redmine API key
        $form = $this->createForm(ProviderCredentialsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userCredentials = $form->getData();

            // Create user
            $user = new User($authUser['email'], $authUser['id'], $organization, $this->clock->now());
            $user->setName($authUser['name']);

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

            $this->oauthSession->clearInviteFlow();

            return $this->render('invite/success.html.twig', [
                'user' => $user,
                'already_exists' => false,
            ]);
        }

        return $this->render('invite/credentials.html.twig', [
            'form' => $form,
            'organization' => $organization,
            'auth_user_name' => $authUser['name'],
            'auth_user_email' => $authUser['email'],
        ]);
    }
}
