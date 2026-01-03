<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Application\Tool\RedmineTool;
use App\Mcp\Domain\Model\Activity;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class LogTimeTool implements RedmineTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Log time spent on an issue.
     */
    #[McpTool(name: 'log_time')]
    public function logTime(
        #[Schema(description: 'The issue ID to log time against')]
        mixed $issue_id,
        #[Schema(description: 'Number of hours to log (must be > 0)')]
        mixed $hours,
        #[Schema(description: 'Comments for the time entry')]
        string $comment,
        #[Schema(description: 'Activity ID (required, read provider://projects/{project_id}/activities to get valid IDs)')]
        mixed $activity_id,
        #[Schema(pattern: '^\d{4}-\d{2}-\d{2}$', description: 'Date in YYYY-MM-DD format. Defaults to today if not specified.')]
        ?string $spent_on = null,
    ): array {
        // Cast to proper types for API compatibility (Cursor sends strings)
        $issue_id = (int) $issue_id;
        $hours = (float) $hours;
        $activity_id = (int) $activity_id;

        $adapter = $this->adapterHolder->getRedmine();

        // Validate hours
        if ($hours <= 0) {
            throw new ToolCallException('Hours must be greater than 0.');
        }

        // Validate activity_id format
        if ($activity_id <= 0) {
            throw new ToolCallException('activity_id must be a positive integer.');
        }

        // Validate activity_id against project's allowed activities
        $issue = $adapter->getIssue($issue_id);
        $projectActivities = $adapter->getProjectActivities($issue->project->id);

        if (!empty($projectActivities)) {
            $allowedIds = array_map(fn (Activity $a) => $a->id, $projectActivities);

            if (!in_array($activity_id, $allowedIds, true)) {
                $allowedList = array_map(
                    fn (Activity $a) => sprintf('%d (%s)', $a->id, $a->name),
                    $projectActivities
                );

                throw new ToolCallException(sprintf('Activity ID %d is not allowed for this project. Allowed activities: %s', $activity_id, implode(', ', $allowedList)));
            }
        }

        // Parse date
        $spentAt = $spent_on ? new \DateTime($spent_on) : new \DateTime('today');

        // Convert hours to seconds
        $seconds = (int) ($hours * 3600);

        // Log time through adapter
        $timeEntry = $adapter->logTime(
            issueId: $issue_id,
            seconds: $seconds,
            comment: $comment,
            spentAt: $spentAt,
            metadata: ['activity_id' => $activity_id]
        );

        return [
            'success' => true,
            'time_entry' => [
                'issue_id' => $timeEntry->issue->id,
                'hours' => $timeEntry->getHours(),
                'comment' => $timeEntry->comment,
                'spent_on' => $timeEntry->spentAt->format('Y-m-d'),
                'activity' => $timeEntry->activity ? $timeEntry->activity->name : null,
            ],
        ];
    }
}
