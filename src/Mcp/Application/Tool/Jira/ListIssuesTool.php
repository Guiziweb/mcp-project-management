<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Jira;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ListIssuesTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * List issues assigned to the current user.
     *
     * Use list_projects tool first to get the list of available projects.
     *
     * @param int|null $project_id Filter by project ID (use list_projects tool to get valid project IDs)
     * @param int      $limit      Maximum number of issues to return (default: 25)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'list_issues')]
    public function listIssues(?int $project_id = null, int $limit = 25): array
    {
        try {
            $adapter = $this->adapterHolder->getJira();
            $issues = $adapter->getIssues($project_id, $limit);

            return [
                'success' => true,
                'issues' => array_map(
                    fn ($issue) => [
                        'id' => $issue->id,
                        'title' => $issue->title,
                        'description' => $issue->description,
                        'status' => $issue->status,
                        'project' => [
                            'id' => $issue->project->id,
                            'name' => $issue->project->name,
                        ],
                        'assignee' => $issue->assignee,
                        'type' => $issue->type,
                        'priority' => $issue->priority,
                    ],
                    $issues
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}