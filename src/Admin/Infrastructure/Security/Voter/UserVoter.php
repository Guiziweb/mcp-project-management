<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, User>
 */
class UserVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        // SUPER_ADMIN can do everything
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        // ORG_ADMIN can only manage users from their organization
        if (!$currentUser->isOrgAdmin()) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // Must be same organization
        if ($targetUser->getOrganization()->getId() !== $currentUser->getOrganization()->getId()) {
            return false;
        }

        // ORG_ADMIN cannot edit/delete another SUPER_ADMIN or themselves to avoid lock-out
        if (self::DELETE === $attribute && $targetUser->getId() === $currentUser->getId()) {
            return false;
        }

        // ORG_ADMIN cannot modify a SUPER_ADMIN
        if ($targetUser->isSuperAdmin()) {
            return false;
        }

        return true;
    }
}
