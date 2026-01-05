<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\WikiPage;

/**
 * Read operations for project wikis.
 *
 * Implemented by providers that support wiki pages.
 */
interface WikiPort
{
    /**
     * Get all wiki pages for a project.
     *
     * @param int $projectId Project identifier
     *
     * @return WikiPage[] List of wiki pages (without content)
     */
    public function getWikiPages(int $projectId): array;

    /**
     * Get a specific wiki page by title.
     *
     * @param int    $projectId Project identifier
     * @param string $pageTitle Wiki page title
     *
     * @return WikiPage The wiki page with content
     */
    public function getWikiPage(int $projectId, string $pageTitle): WikiPage;
}
