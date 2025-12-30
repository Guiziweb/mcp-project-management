<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Entity;

use App\Admin\Infrastructure\Doctrine\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organization')]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(length: 50)]
    private string $providerType;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $size = null;

    /**
     * Provider-specific configuration (e.g., url, workspace).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $providerConfig = [];

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $enabledTools = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'organization')]
    private Collection $users;

    /**
     * @var Collection<int, InviteLink>
     */
    #[ORM\OneToMany(targetEntity: InviteLink::class, mappedBy: 'organization')]
    private Collection $inviteLinks;

    public function __construct(
        string $name,
        ?string $slug,
        string $providerType,
        \DateTimeImmutable $createdAt,
    ) {
        $this->name = $name;
        $this->slug = $slug ?? self::generateSlugFromName($name);
        $this->providerType = $providerType;
        $this->createdAt = $createdAt;
        $this->users = new ArrayCollection();
        $this->inviteLinks = new ArrayCollection();
    }

    /**
     * Generate a URL-safe slug from a name.
     */
    public static function generateSlugFromName(string $name): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        // Ensure minimum length
        if (strlen($slug) < 3) {
            $slug .= '-org';
        }

        return $slug;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @throws \InvalidArgumentException if slug format is invalid
     */
    public function setSlug(string $slug): self
    {
        $length = strlen($slug);
        if ($length < 3 || $length > 50) {
            throw new \InvalidArgumentException('Slug must be between 3 and 50 characters');
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException('Slug must be lowercase alphanumeric with hyphens only (e.g., "my-company")');
        }

        $this->slug = $slug;

        return $this;
    }

    public function getProviderType(): string
    {
        return $this->providerType;
    }

    public function setProviderType(string $providerType): self
    {
        $this->providerType = $providerType;

        return $this;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProviderConfig(): array
    {
        return $this->providerConfig;
    }

    /**
     * @param array<string, mixed> $providerConfig
     */
    public function setProviderConfig(array $providerConfig): self
    {
        $this->providerConfig = $providerConfig;

        return $this;
    }

    /**
     * Get a specific provider config value.
     */
    public function getProviderConfigValue(string $key): mixed
    {
        return $this->providerConfig[$key] ?? null;
    }

    /**
     * Set a specific provider config value.
     */
    public function setProviderConfigValue(string $key, mixed $value): self
    {
        $this->providerConfig[$key] = $value;

        return $this;
    }

    /**
     * Virtual getter for providerConfig['url'].
     */
    public function getProviderUrl(): ?string
    {
        return $this->providerConfig['url'] ?? null;
    }

    /**
     * Virtual setter for providerConfig['url'].
     */
    public function setProviderUrl(?string $url): self
    {
        if (null !== $url && '' !== $url) {
            $this->providerConfig['url'] = rtrim($url, '/');
        } else {
            unset($this->providerConfig['url']);
        }

        return $this;
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
        // Si vide, tous les tools sont autorisés
        if (empty($this->enabledTools)) {
            return true;
        }

        return \in_array($toolName, $this->enabledTools, true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    /**
     * @return Collection<int, InviteLink>
     */
    public function getInviteLinks(): Collection
    {
        return $this->inviteLinks;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
