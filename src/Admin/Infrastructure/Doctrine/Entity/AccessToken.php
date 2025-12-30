<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Entity;

use App\Admin\Infrastructure\Doctrine\Repository\AccessTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * OAuth Access Token for MCP authentication.
 */
#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
#[ORM\Table(name: 'access_token')]
#[ORM\Index(columns: ['token_hash'], name: 'idx_token_hash')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_expires_at')]
#[ORM\Index(columns: ['organization_id'], name: 'idx_organization')]
class AccessToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /**
     * Hash of the token (never store plain token).
     */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    /**
     * Encrypted provider credentials.
     */
    #[ORM\Column(type: 'text')]
    private string $credentials;

    /**
     * Token type: 'access' or 'refresh'.
     */
    #[ORM\Column(length: 20)]
    private string $type;

    /**
     * For refresh tokens: link to the access token it can refresh.
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parentToken = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientInfo = null;

    public function __construct(
        User $user,
        string $tokenHash,
        string $credentials,
        string $type = 'access',
        ?self $parentToken = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $now = $createdAt ?? new \DateTimeImmutable();

        $this->id = Uuid::v4();
        $this->user = $user;
        $this->organization = $user->getOrganization();
        $this->tokenHash = $tokenHash;
        $this->credentials = $credentials;
        $this->type = $type;
        $this->parentToken = $parentToken;
        $this->createdAt = $now;
        $this->expiresAt = 'refresh' === $type
            ? $now->modify('+30 days')
            : $now->modify('+24 hours');
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getCredentials(): string
    {
        return $this->credentials;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isAccessToken(): bool
    {
        return 'access' === $this->type;
    }

    public function isRefreshToken(): bool
    {
        return 'refresh' === $this->type;
    }

    public function getParentToken(): ?self
    {
        return $this->parentToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt < ($now ?? new \DateTimeImmutable());
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoke(?\DateTimeImmutable $at = null): self
    {
        $this->revokedAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function isValid(?\DateTimeImmutable $now = null): bool
    {
        return !$this->isExpired($now) && !$this->isRevoked();
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touch(?\DateTimeImmutable $at = null): self
    {
        $this->lastUsedAt = $at ?? new \DateTimeImmutable();

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

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
