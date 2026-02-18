<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Application\Tool\RedmineTool;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use App\Mcp\Infrastructure\Provider\Redmine\Exception\RedmineApiException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final readonly class ListProjectsTool implements RedmineTool
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
            $adapter = $this->adapterHolder->getRedmine();
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
        } catch (RedmineApiException $e) {
            throw new ToolCallException($e->getMessage());
        }
    }
}
