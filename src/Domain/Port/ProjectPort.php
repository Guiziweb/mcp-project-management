<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Model\Project;

interface ProjectPort
{
    /**
     * Get user's projects.
     *
     * @return Project[]
     */
    public function getProjects(): array;
}
