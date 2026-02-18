<?php

declare(strict_types=1);

namespace App\Mcp\Application\Resource;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use App\Mcp\Infrastructure\Provider\Redmine\Exception\RedmineApiException;
use Mcp\Exception\ResourceReadException;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class ProjectWikiPageResource
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Get a specific wiki page content as a resource.
     *
     * @param string $project_id The project ID
     * @param string $page_title The wiki page title
     */
    public function getProjectWikiPage(string $project_id, string $page_title): TextResourceContents
    {
        try {
            $adapter = $this->adapterHolder->getRedmine();
            $page = $adapter->getWikiPage((int) $project_id, $page_title);

            $data = [
                'title' => $page->title,
                'text' => $page->text,
                'version' => $page->version,
                'author' => $page->author,
                'created_on' => $page->createdOn?->format('Y-m-d H:i:s'),
                'updated_on' => $page->updatedOn?->format('Y-m-d H:i:s'),
            ];

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return new TextResourceContents(
                uri: 'provider://projects/'.$project_id.'/wiki/'.$page_title,
                mimeType: 'application/json',
                text: $json
            );
        } catch (RedmineApiException $e) {
            throw new ResourceReadException($e->getMessage());
        }
    }
}
