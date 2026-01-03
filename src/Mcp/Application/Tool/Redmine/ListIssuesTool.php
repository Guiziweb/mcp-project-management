<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Application\Tool\RedmineTool;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ListIssuesTool implements RedmineTool
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
        #[Schema(description: 'Filter by project ID (use list_projects tool to get valid project IDs)')]
        mixed $project_id = null,
        #[Schema(minimum: 1, maximum: 100, description: 'Maximum number of issues to return (default: 25)')]
        int $limit = 25,
        #[Schema(description: 'Redmine user ID to query (admin-only, null = current user)')]
        mixed $user_id = null,
        #[Schema(description: 'Filter by status ID (read provider://statuses for available IDs)')]
        mixed $status_id = null,
    ): array {
        // Cast to int for API compatibility (Cursor sends strings)
        $project_id = null !== $project_id && '' !== $project_id ? (int) $project_id : null;
        $user_id = null !== $user_id && '' !== $user_id ? (int) $user_id : null;
        $status_id = null !== $status_id && '' !== $status_id ? (int) $status_id : null;

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
