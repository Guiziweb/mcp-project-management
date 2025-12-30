<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Security;

use App\Shared\Domain\UserCredential;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Symfony Security User for MCP API authentication.
 * Wraps provider credentials for stateless Bearer token auth.
 */
final readonly class McpUser implements UserInterface
{
    public function __construct(
        private UserCredential $credential,
    ) {
    }

    /**
     * Get the underlying provider credential.
     */
    public function getCredential(): UserCredential
    {
        return $this->credential;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        assert('' !== $this->credential->userId, 'User ID cannot be empty');

        return $this->credential->userId;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->credential->isAdmin()) {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Nothing to erase - we need the API key
    }
}
