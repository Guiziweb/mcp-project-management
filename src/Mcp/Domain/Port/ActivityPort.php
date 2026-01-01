<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\Activity;

interface ActivityPort
{
    /**
     * Get available activities for a specific project.
     *
     * @return Activity[]
     */
    public function getProjectActivities(int $projectId): array;
}
