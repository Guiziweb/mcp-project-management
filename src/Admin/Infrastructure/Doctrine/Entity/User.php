<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Entity;

use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ORG_ADMIN = 'ROLE_ORG_ADMIN';
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';

    private const VALID_ROLES = [
        self::ROLE_USER,
        self::ROLE_ORG_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    private const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $googleId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [self::ROLE_USER];

    /**
     * Encrypted provider credentials (JSON blob with api_key, email, etc.).
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $providerCredentials = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $enabledTools = [];

    /**
     * User approval status (pending/approved).
     */
    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    /**
     * @var Collection<int, McpSession>
     */
    #[ORM\OneToMany(targetEntity: McpSession::class, mappedBy: 'user')]
    private Collection $sessions;

    public function __construct(
        string $email,
        string $googleId,
        Organization $organization,
        \DateTimeImmutable $createdAt,
    ) {
        $this->email = $email;
        $this->googleId = $googleId;
        $this->organization = $organization;
        $this->createdAt = $createdAt;
        $this->sessions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getGoogleId(): string
    {
        return $this->googleId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    /**
     * @param array<string> $roles
     *
     * @throws \InvalidArgumentException if any role is invalid
     */
    public function setRoles(array $roles): self
    {
        foreach ($roles as $role) {
            if (!\in_array($role, self::VALID_ROLES, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid role "%s". Valid roles are: %s', $role, implode(', ', self::VALID_ROLES)));
            }
        }

        $this->roles = $roles;

        return $this;
    }

    /**
     * Can access admin panel (org admin or super admin).
     */
    public function isAdmin(): bool
    {
        return $this->isOrgAdmin() || $this->isSuperAdmin();
    }

    /**
     * Admin of their own organization only.
     */
    public function isOrgAdmin(): bool
    {
        return \in_array(self::ROLE_ORG_ADMIN, $this->roles, true);
    }

    /**
     * Platform super admin - can see all organizations.
     */
    public function isSuperAdmin(): bool
    {
        return \in_array(self::ROLE_SUPER_ADMIN, $this->roles, true);
    }

    public function getProviderCredentials(): ?string
    {
        return $this->providerCredentials;
    }

    public function setProviderCredentials(?string $providerCredentials): self
    {
        $this->providerCredentials = $providerCredentials;

        return $this;
    }

    public function hasProviderCredentials(): bool
    {
        return null !== $this->providerCredentials;
    }

    /**
     * @return array<string>
     */
    public function getEnabledTools(): array
    {
        return $this->enabledTools;
    }

    /**
     * @param array<string> $enabledTools
     */
    public function setEnabledTools(array $enabledTools): self
    {
        $this->enabledTools = $enabledTools;

        return $this;
    }

    public function hasToolEnabled(string $toolName): bool
    {
        // D'abord check l'orga
        if (!$this->organization->hasToolEnabled($toolName)) {
            return false;
        }

        // Si user a des overrides, check
        if (!empty($this->enabledTools)) {
            return \in_array($toolName, $this->enabledTools, true);
        }

        // Sinon, hérite de l'orga
        return true;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!\in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid status "%s". Valid statuses are: %s', $status, implode(', ', self::VALID_STATUSES)));
        }

        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function isApproved(): bool
    {
        return self::STATUS_APPROVED === $this->status;
    }

    public function approve(): self
    {
        $this->status = self::STATUS_APPROVED;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function updateLastSeenAt(?\DateTimeImmutable $at = null): self
    {
        $this->lastSeenAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, McpSession>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    /**
     * Check if user has an active MCP session (activity within last 5 minutes).
     */
    public function isConnected(?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();
        $threshold = $now->modify('-5 minutes');

        foreach ($this->sessions as $session) {
            if ($session->getLastActivityAt() >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
