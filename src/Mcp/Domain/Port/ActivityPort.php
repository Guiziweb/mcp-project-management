<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\Activity;

interface ActivityPort
{
    /**
     * Get available activities for time tracking.
     *
     * @return Activity[]
     */
    public function getActivities(): array;
}
