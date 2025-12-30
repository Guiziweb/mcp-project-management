<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Service;

use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Registry of all available MCP tools.
 * Automatically discovers tools tagged with 'mcp.tool'.
 */
class ToolRegistry
{
    /**
     * @var array<string, string> Tool name => Tool description
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
     * @return array<string, string> Tool name => description (for ChoiceField)
     */
    public function getToolChoices(): array
    {
        $choices = [];
        foreach ($this->tools as $name => $description) {
            $label = $description ?: $this->humanizeName($name);
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
        $reflection = new \ReflectionClass($tool);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(McpTool::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $name = $instance->name;
                $description = $instance->description ?? '';
                $this->tools[$name] = $description;
            }
        }
    }

    private function humanizeName(string $name): string
    {
        // list_issues -> List Issues
        return ucwords(str_replace('_', ' ', $name));
    }
}
