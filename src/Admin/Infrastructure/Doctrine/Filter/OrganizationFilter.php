<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Filter;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL Filter for organization-based multi-tenancy.
 *
 * When enabled, automatically adds organization_id constraints to queries
 * for entities that have an organization relationship.
 *
 * Usage:
 *   $em->getFilters()->enable('organization');
 *   $em->getFilters()->getFilter('organization')->setOrganization($org);
 */
class OrganizationFilter extends SQLFilter
{
    private ?string $organizationId = null;

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (null === $this->organizationId) {
            return '';
        }

        // Skip Organization entity itself
        if (Organization::class === $targetEntity->getName()) {
            return '';
        }

        // Check if entity has organization field
        if (!$targetEntity->hasAssociation('organization')) {
            return '';
        }

        $association = $targetEntity->getAssociationMapping('organization');

        // Only filter ManyToOne relationships to Organization
        if (Organization::class !== $association['targetEntity']) {
            return '';
        }

        $columnName = $association['joinColumns'][0]['name'] ?? 'organization_id';

        return sprintf('%s.%s = %s', $targetTableAlias, $columnName, $this->organizationId);
    }

    public function setOrganization(Organization $organization): void
    {
        $this->organizationId = $this->getConnection()->quote((string) $organization->getId());
    }

    public function setOrganizationId(string $organizationId): void
    {
        $this->organizationId = $this->getConnection()->quote($organizationId);
    }
}
