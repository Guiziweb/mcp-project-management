<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Jira;

use App\Mcp\Domain\Model\TimeEntry;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ListTimeEntriesTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Get time entries (worklogs) with optional date filtering and total calculation.
     *
     * Perfect for monthly time tracking and work hour analysis.
     * Returns daily, weekly, and project breakdowns.
     *
     * @param string|null $from Start date (YYYY-MM-DD)
     * @param string|null $to   End date (YYYY-MM-DD)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'list_time_entries')]
    public function listTimeEntries(
        #[Schema(pattern: '^\d{4}-\d{2}-\d{2}$')]
        ?string $from = null,
        #[Schema(pattern: '^\d{4}-\d{2}-\d{2}$')]
        ?string $to = null,
    ): array {
        try {
            $adapter = $this->adapterHolder->getJira();

            // Parse dates
            $fromDate = $from ? new \DateTime($from) : new \DateTime('-30 days');
            $toDate = $to ? new \DateTime($to) : new \DateTime('today');

            // Get time entries
            $entries = $adapter->getTimeEntries($fromDate, $toDate);

            // Aggregate by day
            $byDay = $this->aggregateByDay($entries);

            // Aggregate by project
            $byProject = $this->aggregateByProject($entries);

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

    /**
     * Aggregate time entries by day.
     *
     * @param TimeEntry[] $entries
     *
     * @return array<string, array{date: string, hours: float, entries: TimeEntry[]}>
     */
    private function aggregateByDay(array $entries): array
    {
        $byDay = [];
        foreach ($entries as $entry) {
            $dateKey = $entry->spentAt->format('Y-m-d');

            if (!isset($byDay[$dateKey])) {
                $byDay[$dateKey] = [
                    'date' => $dateKey,
                    'hours' => 0.0,
                    'entries' => [],
                ];
            }

            $byDay[$dateKey]['hours'] += $entry->getHours();
            $byDay[$dateKey]['entries'][] = $entry;
        }

        ksort($byDay);

        return $byDay;
    }

    /**
     * Aggregate time entries by project.
     *
     * @param TimeEntry[] $entries
     *
     * @return array<int, array{project_id: int, project_name: string, hours: float, entries: TimeEntry[]}>
     */
    private function aggregateByProject(array $entries): array
    {
        $byProject = [];
        foreach ($entries as $entry) {
            $projectId = $entry->issue->project->id;

            if (!isset($byProject[$projectId])) {
                $byProject[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $entry->issue->project->name,
                    'hours' => 0.0,
                    'entries' => [],
                ];
            }

            $byProject[$projectId]['hours'] += $entry->getHours();
            $byProject[$projectId]['entries'][] = $entry;
        }

        return $byProject;
    }
}