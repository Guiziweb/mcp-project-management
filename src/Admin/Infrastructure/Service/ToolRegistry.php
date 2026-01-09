<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Service;

use App\Admin\Infrastructure\Doctrine\Entity\User;
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
     * @var array<string, array{description: string, callable: array{class-string, string}}>
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
    public function registerTools(Builder $builder, User $user): void
    {
        foreach ($this->tools as $name => $tool) {
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

    private function discoverToolsFromClass(object $tool): void
    {
        if (!$tool instanceof RedmineTool) {
            return;
        }

        $reflection = new \ReflectionClass($tool);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(McpTool::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->tools[$instance->name] = [
                    'description' => $instance->description ?? '',
                    'callable' => [$reflection->getName(), $method->getName()],
                ];
            }
        }
    }

    private function humanizeName(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}
