<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Minimal UserProvider for Symfony Security.
 *
 * In stateless JWT architecture, users are created directly by JwtAuthenticator
 * from the token payload. This provider only handles refresh operations.
 *
 * @implements UserProviderInterface<User>
 */
final readonly class UserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // In stateless architecture, users are loaded by JwtAuthenticator
        // This method should not be called directly
        throw new UnsupportedUserException('Users must be loaded via JWT token');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Expected instance of %s, got %s', User::class, get_class($user)));
        }

        // In stateless architecture, we don't refresh - just return the same user
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
