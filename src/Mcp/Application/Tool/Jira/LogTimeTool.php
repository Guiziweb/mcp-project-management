<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Jira;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class LogTimeTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Log time spent on an issue (worklog).
     *
     * @param int         $issue_id The issue ID to log time against
     * @param float       $hours    Number of hours to log
     * @param string      $comment  Comments for the time entry
     * @param string|null $spent_on Date in YYYY-MM-DD format (e.g., "2025-10-07"). Defaults to today if not specified.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'log_time')]
    public function logTime(
        int $issue_id,
        float $hours,
        string $comment,
        ?string $spent_on = null,
    ): array {
        try {
            $adapter = $this->adapterHolder->getJira();

            // Validate hours
            if ($hours <= 0) {
                return [
                    'success' => false,
                    'error' => 'Hours must be greater than 0.',
                ];
            }

            // Parse date
            $spentAt = $spent_on ? new \DateTime($spent_on) : new \DateTime('today');

            // Convert hours to seconds
            $seconds = (int) ($hours * 3600);

            // Log time through adapter (no activity_id needed for Jira)
            $timeEntry = $adapter->logTime(
                issueId: $issue_id,
                seconds: $seconds,
                comment: $comment,
                spentAt: $spentAt,
                metadata: []
            );

            // Format response
            return [
                'success' => true,
                'time_entry' => [
                    'id' => $timeEntry->id,
                    'issue_id' => $timeEntry->issue->id,
                    'hours' => $timeEntry->getHours(),
                    'comment' => $timeEntry->comment,
                    'spent_on' => $timeEntry->spentAt->format('Y-m-d'),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}