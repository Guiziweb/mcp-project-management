<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Model\TimeEntry;

/**
 * Read-only time entry operations.
 *
 * Implemented by all providers that support viewing time entries.
 */
interface TimeEntryReadPort
{
    /**
     * Get user's time entries within a date range.
     *
     * @param \DateTimeInterface $from   Start date
     * @param \DateTimeInterface $to     End date
     * @param int|null           $userId User ID to query (admin-only, null = current user)
     *
     * @return TimeEntry[]
     */
    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array;
}
