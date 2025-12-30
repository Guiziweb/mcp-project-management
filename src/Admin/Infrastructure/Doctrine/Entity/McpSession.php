<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Entity;

use App\Admin\Infrastructure\Doctrine\Repository\McpSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: McpSessionRepository::class)]
#[ORM\Table(name: 'mcp_session')]
#[ORM\Index(columns: ['last_activity_at'], name: 'idx_session_last_activity')]
#[ORM\Index(columns: ['organization_id'], name: 'idx_session_organization')]
class McpSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(type: 'text')]
    private string $data;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientInfo = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastActivityAt;

    public function __construct(
        Uuid $id,
        User $user,
        string $data = '{}',
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $now = $createdAt ?? new \DateTimeImmutable();

        $this->id = $id;
        $this->user = $user;
        $this->organization = $user->getOrganization();
        $this->data = $data;
        $this->createdAt = $now;
        $this->lastActivityAt = $now;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getClientInfo(): ?string
    {
        return $this->clientInfo;
    }

    public function setClientInfo(?string $clientInfo): self
    {
        $this->clientInfo = $clientInfo;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function touch(?\DateTimeImmutable $at = null): self
    {
        $this->lastActivityAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function isExpired(int $ttlSeconds = 3600, ?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $this->lastActivityAt->getTimestamp();

        return $diff > $ttlSeconds;
    }
}
