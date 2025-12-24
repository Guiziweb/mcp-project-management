<?php

declare(strict_types=1);

namespace App\Domain\Issue;

interface IssueReadPort
{
    /**
     * Get user's issues, optionally filtered by project and status.
     *
     * @param int|null        $projectId Project ID to filter by
     * @param int             $limit     Maximum number of issues to return
     * @param int|null        $userId    User ID to query (null = current user)
     * @param string|int|null $statusId  Status filter (null = open issues only)
     *
     * @return Issue[]
     */
    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null, string|int|null $statusId = null): array;

    /**
     * Get a specific issue by ID.
     */
    public function getIssue(int $issueId): Issue;
}
