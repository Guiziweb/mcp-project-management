<?php

declare(strict_types=1);

namespace App\Tools;

use App\Domain\TimeEntry\TimeEntryService;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ListTimeEntriesTool
{
    public function __construct(
        private readonly TimeEntryService $timeEntryService,
    ) {
    }

    /**
     * Get time entries with optional date filtering and total calculation.
     *
     * Perfect for monthly time tracking and work hour analysis.
     * Returns daily, weekly, and project breakdowns.
     *
     * @param string|null $from    Start date (YYYY-MM-DD)
     * @param string|null $to      End date (YYYY-MM-DD)
     * @param int|null    $user_id Redmine user ID to query (admin-only, null = current user)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'list_time_entries')]
    public function listTimeEntries(
        ?string $from = null,
        ?string $to = null,
        ?int $user_id = null,
    ): array {
        try {
            // Parse dates
            $fromDate = $from ? new \DateTime($from) : new \DateTime('-30 days');
            $toDate = $to ? new \DateTime($to) : new \DateTime('today');

            // Get aggregated data
            $byDay = $this->timeEntryService->getEntriesByDay($fromDate, $toDate, $user_id);
            $byProject = $this->timeEntryService->getEntriesByProject($fromDate, $toDate, $user_id);

            // Calculate totals
            $totalHours = 0.0;
            $totalEntries = 0;
            $weeklyTotals = [];

            foreach ($byDay as $day) {
                $totalHours += $day['hours'];
                $totalEntries += count($day['entries']);

                // Group by week (ISO 8601: YYYY-W##)
                $weekKey = (new \DateTime($day['date']))->format('Y-\WW');
                $weeklyTotals[$weekKey] = ($weeklyTotals[$weekKey] ?? 0) + $day['hours'];
            }

            // Format daily breakdown
            $dailyBreakdown = [];
            foreach ($byDay as $day) {
                $dailyBreakdown[$day['date']] = [
                    'hours' => round($day['hours'], 2),
                    'entries' => array_map(
                        fn ($entry) => [
                            'id' => $entry->id,
                            'issue_id' => $entry->issue->id,
                            'issue_title' => $entry->issue->title,
                            'project' => $entry->issue->project->name,
                            'hours' => $entry->getHours(),
                            'comment' => $entry->comment,
                            'activity' => $entry->activity?->name,
                        ],
                        $day['entries']
                    ),
                ];
            }

            // Format project breakdown
            $projectBreakdown = [];
            foreach ($byProject as $project) {
                $projectBreakdown[$project['project_name']] = round($project['hours'], 2);
            }

            $workingDays = count($byDay);
            $averageHoursPerDay = $workingDays > 0 ? $totalHours / $workingDays : 0;

            return [
                'success' => true,
                'summary' => [
                    'total_hours' => round($totalHours, 2),
                    'total_entries' => $totalEntries,
                    'working_days' => $workingDays,
                    'average_hours_per_day' => round($averageHoursPerDay, 2),
                    'project_breakdown' => $projectBreakdown,
                    'weekly_breakdown' => array_map(fn ($h) => round($h, 2), $weeklyTotals),
                ],
                'daily_breakdown' => $dailyBreakdown,
                'period' => [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d'),
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
