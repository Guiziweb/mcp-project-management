<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\InviteLink;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, InviteLink>
 */
class InviteLinkVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const CREATE = 'CREATE';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::DELETE], true)
            && $subject instanceof InviteLink;
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

        // ORG_ADMIN can only manage invite links from their organization
        if (!$currentUser->isOrgAdmin()) {
            return false;
        }

        /** @var InviteLink $inviteLink */
        $inviteLink = $subject;

        // Must be same organization
        return $inviteLink->getOrganization()->getId() === $currentUser->getOrganization()->getId();
    }
}
