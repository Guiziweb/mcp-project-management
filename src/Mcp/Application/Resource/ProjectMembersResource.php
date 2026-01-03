<?php

declare(strict_types=1);

namespace App\Mcp\Application\Resource;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ProjectMembersResource
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Get project members as a resource.
     *
     * @param string $project_id The project ID
     */
    public function getProjectMembers(string $project_id): TextResourceContents
    {
        $adapter = $this->adapterHolder->getRedmine();
        $members = $adapter->getProjectMembers((int) $project_id);

        $data = array_map(
            fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'roles' => $member->roles,
            ],
            $members
        );

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new TextResourceContents(
            uri: 'provider://projects/'.$project_id.'/members',
            mimeType: 'application/json',
            text: $json
        );
    }
}
