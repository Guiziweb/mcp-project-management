<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\Project;

interface ProjectPort
{
    /**
     * Get user's projects.
     *
     * @return Project[]
     */
    public function getProjects(): array;
}
