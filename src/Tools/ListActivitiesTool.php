<?php

declare(strict_types=1);

namespace App\Tools;

use App\Domain\Port\TimeTrackingPort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final readonly class ListActivitiesTool
{
    public function __construct(
        private TimeTrackingPort $adapter,
    ) {
    }

    /**
     * List all available time entry activities.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'list_activities')]
    public function listActivities(): array
    {
        try {
            $activities = $this->adapter->getActivities();

            return [
                'success' => true,
                'activities' => array_map(
                    fn ($activity) => [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'is_default' => $activity->isDefault,
                        'active' => $activity->active,
                    ],
                    $activities
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
