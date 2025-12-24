<?php

declare(strict_types=1);

namespace App\Resources;

use App\Domain\Status\StatusPort;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class StatusesResource
{
    public function __construct(
        private readonly StatusPort $adapter,
    ) {
    }

    /**
     * Get available issue statuses as a resource.
     */
    public function getStatuses(): TextResourceContents
    {
        $statuses = $this->adapter->getStatuses();

        $data = array_map(
            fn ($status) => [
                'id' => $status->id,
                'name' => $status->name,
                'is_closed' => $status->isClosed,
            ],
            $statuses
        );

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new TextResourceContents(
            uri: 'provider://statuses',
            mimeType: 'application/json',
            text: $json
        );
    }
}