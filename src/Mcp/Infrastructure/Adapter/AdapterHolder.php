<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Adapter;

use App\Mcp\Infrastructure\Provider\Jira\JiraAdapter;
use App\Mcp\Infrastructure\Provider\Monday\MondayAdapter;
use App\Mcp\Infrastructure\Provider\Redmine\RedmineAdapter;

/**
 * Request-scoped holder for the current user's adapter.
 *
 * Set by McpController before registering tools.
 * Tools inject this holder and call the typed getter for their provider.
 */
final class AdapterHolder
{
    private ?object $adapter = null;

    /**
     * Set the adapter for the current request.
     */
    public function set(object $adapter): void
    {
        $this->adapter = $adapter;
    }

    /**
     * Get the Redmine adapter (type-safe).
     *
     * @throws \LogicException if adapter is not set or not RedmineAdapter
     */
    public function getRedmine(): RedmineAdapter
    {
        if (!$this->adapter instanceof RedmineAdapter) {
            throw new \LogicException('Adapter is not set or not a RedmineAdapter');
        }

        return $this->adapter;
    }

    /**
     * Get the Jira adapter (type-safe).
     *
     * @throws \LogicException if adapter is not set or not JiraAdapter
     */
    public function getJira(): JiraAdapter
    {
        if (!$this->adapter instanceof JiraAdapter) {
            throw new \LogicException('Adapter is not set or not a JiraAdapter');
        }

        return $this->adapter;
    }

    /**
     * Get the Monday adapter (type-safe).
     *
     * @throws \LogicException if adapter is not set or not MondayAdapter
     */
    public function getMonday(): MondayAdapter
    {
        if (!$this->adapter instanceof MondayAdapter) {
            throw new \LogicException('Adapter is not set or not a MondayAdapter');
        }

        return $this->adapter;
    }
}
