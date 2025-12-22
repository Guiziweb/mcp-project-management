<?php

declare(strict_types=1);

namespace App\Domain\Issue;

interface IssueReadPort
{
    /**
     * Get user's issues, optionally filtered by project.
     *
     * @param int|null $projectId Project ID to filter by (optional)
     * @param int      $limit     Maximum number of issues to return
     * @param int|null $userId    User ID to query (admin-only, null = current user)
     *
     * @return Issue[]
     */
    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null): array;

    /**
     * Get a specific issue by ID.
     */
    public function getIssue(int $issueId): Issue;
}
