<?php

declare(strict_types=1);

namespace App\Mcp\Application\Resource;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ProjectActivitiesResource
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Get project activities as a resource.
     *
     * @param string $project_id The project ID
     */
    public function getProjectActivities(string $project_id): TextResourceContents
    {
        $adapter = $this->adapterHolder->getRedmine();
        $activities = $adapter->getProjectActivities((int) $project_id);

        $data = array_map(
            fn ($activity) => [
                'id' => $activity->id,
                'name' => $activity->name,
            ],
            $activities
        );

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new TextResourceContents(
            uri: 'provider://projects/'.$project_id.'/activities',
            mimeType: 'application/json',
            text: $json
        );
    }
}
