<?php

declare(strict_types=1);

namespace App\Resources;

use App\Domain\Activity\ActivityPort;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ActivitiesResource
{
    public function __construct(
        private readonly ActivityPort $adapter,
    ) {
    }

    /**
     * Get available time entry activities as a resource.
     */
    public function getActivities(): TextResourceContents
    {
        $activities = $this->adapter->getActivities();

        $data = array_map(
            fn ($activity) => [
                'id' => $activity->id,
                'name' => $activity->name,
                'is_default' => $activity->isDefault,
            ],
            $activities
        );

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new TextResourceContents(
            uri: 'provider://activities',
            mimeType: 'application/json',
            text: $json
        );
    }
}
