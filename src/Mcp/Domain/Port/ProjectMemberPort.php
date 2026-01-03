<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\ProjectMember;

/**
 * Read operations for project members.
 *
 * Implemented by providers that support listing project members.
 */
interface ProjectMemberPort
{
    /**
     * Get members of a project.
     *
     * @param int $projectId Project identifier
     *
     * @return ProjectMember[]
     */
    public function getProjectMembers(int $projectId): array;
}
