<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Service;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Mcp\Application\Tool\JiraTool;
use App\Mcp\Application\Tool\MondayTool;
use App\Mcp\Application\Tool\RedmineTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\Builder;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Registry of all available MCP tools.
 * Automatically discovers tools tagged with 'mcp.tool'.
 */
class ToolRegistry
{
    /**
     * @var array<string, array{description: string, callable: array{class-string, string}, provider: string}>
     */
    private array $tools = [];

    /**
     * @param iterable<object> $mcpTools
     */
    public function __construct(
        #[TaggedIterator('mcp.tool')]
        iterable $mcpTools,
    ) {
        foreach ($mcpTools as $tool) {
            $this->discoverToolsFromClass($tool);
        }
    }

    /**
     * Register tools for a user based on their permissions.
     */
    public function registerTools(Builder $builder, User $user, string $provider): void
    {
        foreach ($this->tools as $name => $tool) {
            if ($tool['provider'] !== $provider) {
                continue;
            }

            if ($user->hasToolEnabled($name)) {
                $builder->addTool($tool['callable']);
            }
        }
    }

    /**
     * @return array<string, string> Tool name => description (for ChoiceField)
     */
    public function getToolChoices(): array
    {
        $choices = [];
        foreach ($this->tools as $name => $tool) {
            $label = $tool['description'] ?: $this->humanizeName($name);
            $choices[$label] = $name;
        }
        ksort($choices);

        return $choices;
    }

    /**
     * @return array<string> List of tool names
     */
    public function getToolNames(): array
    {
        return array_keys($this->tools);
    }

    /**
     * @return array<string> List of tool names for a specific provider
     */
    public function getToolNamesByProvider(string $provider): array
    {
        return array_keys(array_filter(
            $this->tools,
            fn (array $tool) => $tool['provider'] === $provider
        ));
    }

    private function discoverToolsFromClass(object $tool): void
    {
        $reflection = new \ReflectionClass($tool);
        $provider = $this->extractProvider($tool);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(McpTool::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->tools[$instance->name] = [
                    'description' => $instance->description ?? '',
                    'callable' => [$reflection->getName(), $method->getName()],
                    'provider' => $provider,
                ];
            }
        }
    }

    /**
     * Extract provider from tool's marker interface.
     */
    private function extractProvider(object $tool): string
    {
        return match (true) {
            $tool instanceof RedmineTool => 'redmine',
            $tool instanceof JiraTool => 'jira',
            $tool instanceof MondayTool => 'monday',
            default => 'unknown',
        };
    }

    private function humanizeName(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}
