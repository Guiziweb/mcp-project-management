<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Adapter;

use App\Mcp\Infrastructure\Provider\Redmine\RedmineAdapter;

/**
 * Request-scoped holder for the current user's Redmine adapter.
 *
 * Set by McpController before registering tools.
 */
final class AdapterHolder
{
    private ?RedmineAdapter $adapter = null;

    /**
     * Set the adapter for the current request.
     */
    public function set(RedmineAdapter $adapter): void
    {
        $this->adapter = $adapter;
    }

    /**
     * Get the Redmine adapter.
     *
     * @throws \LogicException if adapter is not set
     */
    public function getRedmine(): RedmineAdapter
    {
        if (null === $this->adapter) {
            throw new \LogicException('Adapter is not set');
        }

        return $this->adapter;
    }
}
