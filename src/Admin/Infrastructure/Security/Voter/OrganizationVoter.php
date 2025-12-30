<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Organization>
 */
class OrganizationVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';
    public const CREATE = 'CREATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE], true)
            && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        // Only SUPER_ADMIN can manage organizations
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        /** @var Organization $organization */
        $organization = $subject;

        // ORG_ADMIN can only VIEW their own organization (not edit/delete/create)
        if (self::VIEW === $attribute && $currentUser->isOrgAdmin()) {
            return $organization->getId() === $currentUser->getOrganization()->getId();
        }

        return false;
    }
}
