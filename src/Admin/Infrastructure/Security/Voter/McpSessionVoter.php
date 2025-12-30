<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\McpSession;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, McpSession>
 */
class McpSessionVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::DELETE], true)
            && $subject instanceof McpSession;
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

        // ORG_ADMIN can only view/delete sessions from their organization's users
        if (!$currentUser->isOrgAdmin()) {
            return false;
        }

        /** @var McpSession $session */
        $session = $subject;
        $sessionUser = $session->getUser();

        // Must be same organization
        return $sessionUser->getOrganization()->getId() === $currentUser->getOrganization()->getId();
    }
}
