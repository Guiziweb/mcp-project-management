<?php

declare(strict_types=1);

namespace App\Domain\Port;

/**
 * Full time entry capabilities (read + write).
 *
 * This interface combines TimeEntryReadPort and TimeEntryWritePort
 * for providers that support both reading and writing time entries.
 *
 * Use the specific interfaces (TimeEntryReadPort, TimeEntryWritePort)
 * when you need to check capabilities or work with partial implementations.
 */
interface TimeEntryPort extends TimeEntryReadPort, TimeEntryWritePort
{
}
