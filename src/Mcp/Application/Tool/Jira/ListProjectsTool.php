<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Jira;

use App\Mcp\Application\Tool\JiraTool;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final readonly class ListProjectsTool implements JiraTool
{
    public function __construct(
        private AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * List all projects the current user has access to.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'list_projects')]
    public function listProjects(): array
    {
        try {
            $adapter = $this->adapterHolder->getJira();
            $projects = $adapter->getProjects();

            return [
                'success' => true,
                'projects' => array_map(
                    fn ($project) => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'parent' => $project->parent ? [
                            'id' => $project->parent->id,
                            'name' => $project->parent->name,
                        ] : null,
                    ],
                    $projects
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
