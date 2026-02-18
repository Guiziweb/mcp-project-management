<?php

declare(strict_types=1);

namespace App\Mcp\Application\Resource;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use App\Mcp\Infrastructure\Provider\Redmine\Exception\RedmineApiException;
use Mcp\Exception\ResourceReadException;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ProjectWikiPagesResource
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Get project wiki pages as a resource.
     *
     * @param string $project_id The project ID
     */
    public function getProjectWikiPages(string $project_id): TextResourceContents
    {
        try {
            $adapter = $this->adapterHolder->getRedmine();
            $pages = $adapter->getWikiPages((int) $project_id);

            $data = array_map(
                fn ($page) => [
                    'title' => $page->title,
                    'version' => $page->version,
                    'created_on' => $page->createdOn?->format('Y-m-d H:i:s'),
                    'updated_on' => $page->updatedOn?->format('Y-m-d H:i:s'),
                ],
                $pages
            );

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return new TextResourceContents(
                uri: 'provider://projects/'.$project_id.'/wiki',
                mimeType: 'application/json',
                text: $json
            );
        } catch (RedmineApiException $e) {
            throw new ResourceReadException($e->getMessage());
        }
    }
}
