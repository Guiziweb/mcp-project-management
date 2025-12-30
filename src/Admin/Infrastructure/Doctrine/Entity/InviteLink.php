<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Entity;

use App\Admin\Infrastructure\Doctrine\Repository\InviteLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: InviteLinkRepository::class)]
#[ORM\Table(name: 'invite_link')]
class InviteLink
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $token;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'inviteLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column]
    private int $usesCount = 0;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    public function __construct(
        Organization $organization,
        User $createdBy,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->token = Uuid::v4();
        $this->organization = $organization;
        $this->createdBy = $createdBy;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getToken(): Uuid
    {
        return $this->token;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): self
    {
        $this->maxUses = $maxUses;

        return $this;
    }

    public function getUsesCount(): int
    {
        return $this->usesCount;
    }

    public function incrementUsesCount(): self
    {
        ++$this->usesCount;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function isValid(?\DateTimeImmutable $now = null): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->expiresAt < ($now ?? new \DateTimeImmutable())) {
            return false;
        }

        if (null !== $this->maxUses && $this->usesCount >= $this->maxUses) {
            return false;
        }

        return true;
    }

    public function use(?\DateTimeImmutable $now = null): self
    {
        if (!$this->isValid($now)) {
            throw new \LogicException('Cannot use an invalid invite link');
        }

        $this->incrementUsesCount();

        return $this;
    }
}
