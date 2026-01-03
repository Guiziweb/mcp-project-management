<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Session;

use App\Admin\Infrastructure\Doctrine\Entity\McpSession;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\McpSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine-based session store for MCP.
 *
 * Stores sessions in database instead of files, allowing:
 * - Admin visibility into active sessions
 * - Session metadata (user, client info)
 * - Better cleanup and monitoring
 */
class DoctrineSessionStore implements SessionStoreInterface
{
    private ?User $currentUser = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly McpSessionRepository $sessionRepository,
        private readonly int $ttl = 3600,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * Set the current user for new sessions.
     * Must be called before write() for new sessions.
     */
    public function setCurrentUser(User $user): void
    {
        $this->currentUser = $user;
    }

    public function exists(Uuid $id): bool
    {
        $session = $this->sessionRepository->findById($id);

        if (null === $session) {
            return false;
        }

        return !$session->isExpired($this->ttl);
    }

    public function read(Uuid $id): string|false
    {
        $session = $this->sessionRepository->findById($id);

        if (null === $session) {
            return false;
        }

        if ($session->isExpired($this->ttl)) {
            $this->em->remove($session);
            $this->em->flush();

            return false;
        }

        return $session->getData();
    }

    public function write(Uuid $id, string $data): bool
    {
        $session = $this->sessionRepository->findById($id);

        if (null === $session) {
            if (null === $this->currentUser) {
                throw new \LogicException('Cannot create session without a current user. Call setCurrentUser() first.');
            }

            // Extract client name from MCP session data (set by InitializeHandler)
            $clientName = $this->extractClientName($data);

            // Delete old sessions for same user + client (replace instead of accumulate)
            if (null !== $clientName) {
                $this->sessionRepository->deleteByUserAndClientName($this->currentUser, $clientName);
            }

            $session = new McpSession($id, $this->currentUser, $data);
            $session->setClientInfo($clientName);

            $this->em->persist($session);
        } else {
            $session->setData($data);
            $session->touch();

            // Update clientInfo if not set yet (initialize just happened)
            if (null === $session->getClientInfo()) {
                $clientName = $this->extractClientName($data);
                if (null !== $clientName) {
                    $session->setClientInfo($clientName);
                }
            }
        }

        // Also update user's last seen
        if (null !== $this->currentUser) {
            $this->currentUser->updateLastSeenAt();
        }

        $this->em->flush();

        return true;
    }

    /**
     * Extract client name from MCP session data.
     * The InitializeHandler stores client_info with name and version.
     */
    private function extractClientName(string $data): ?string
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            return $decoded['client_info']['name'] ?? null;
        } catch (\JsonException) {
            return null;
        }
    }

    public function destroy(Uuid $id): bool
    {
        $session = $this->sessionRepository->findById($id);

        if (null !== $session) {
            $this->em->remove($session);
            $this->em->flush();
        }

        return true;
    }

    /**
     * @return Uuid[]
     */
    public function gc(): array
    {
        $expiredBefore = $this->now()->modify(sprintf('-%d seconds', $this->ttl));

        $expiredSessions = $this->em->createQueryBuilder()
            ->select('s')
            ->from(McpSession::class, 's')
            ->where('s.lastActivityAt < :expiredBefore')
            ->setParameter('expiredBefore', $expiredBefore)
            ->getQuery()
            ->getResult();

        $deletedIds = [];

        foreach ($expiredSessions as $session) {
            $deletedIds[] = $session->getId();
            $this->em->remove($session);
        }

        $this->em->flush();

        return $deletedIds;
    }

    private function now(): \DateTimeImmutable
    {
        if (null !== $this->clock) {
            return $this->clock->now();
        }

        return new \DateTimeImmutable();
    }
}
