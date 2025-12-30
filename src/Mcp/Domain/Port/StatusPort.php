<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\Status;

interface StatusPort
{
    /**
     * Get available issue statuses.
     *
     * @return Status[]
     */
    public function getStatuses(): array;
}
