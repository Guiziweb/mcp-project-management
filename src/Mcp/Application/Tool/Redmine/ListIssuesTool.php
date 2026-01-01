<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ListIssuesTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * List issues assigned to a user from ONE specific project.
     */
    #[McpTool(name: 'list_issues')]
    public function listIssues(
        #[Schema(minimum: 1, description: 'Filter by project ID (use list_projects tool to get valid project IDs)')]
        ?int $project_id = null,
        #[Schema(minimum: 1, maximum: 100, description: 'Maximum number of issues to return (default: 25)')]
        int $limit = 25,
        #[Schema(minimum: 1, description: 'Redmine user ID to query (admin-only, null = current user)')]
        ?int $user_id = null,
        #[Schema(minimum: 1, description: 'Filter by status ID (read provider://statuses for available IDs)')]
        mixed $status_id = null,
    ): array
    {
        $adapter = $this->adapterHolder->getRedmine();
        $issues = $adapter->getIssues($project_id, $limit, $user_id, $status_id);

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
    }
}