<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Model\Activity;

interface ActivityPort
{
    /**
     * Get available activities for time tracking.
     *
     * @return Activity[]
     */
    public function getActivities(): array;
}