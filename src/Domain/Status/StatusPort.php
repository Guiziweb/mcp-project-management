<?php

declare(strict_types=1);

namespace App\Domain\Status;

interface StatusPort
{
    /**
     * Get available issue statuses.
     *
     * @return Status[]
     */
    public function getStatuses(): array;
}
