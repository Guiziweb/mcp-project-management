<?php

declare(strict_types=1);

namespace App\Domain\Issue;

/**
 * Write operations for issues.
 *
 * Implemented by providers that support modifying issues.
 */
interface IssueWritePort
{
    /**
     * Add a comment to an issue.
     *
     * @param int    $issueId Issue identifier
     * @param string $comment The comment content
     * @param bool   $private Whether the comment is private (provider-specific)
     */
    public function addComment(int $issueId, string $comment, bool $private = false): void;

    /**
     * Update a comment on an issue.
     *
     * @param int    $commentId Comment identifier
     * @param string $comment   The new comment content
     */
    public function updateComment(int $commentId, string $comment): void;

    /**
     * Delete a comment from an issue.
     *
     * @param int $commentId Comment identifier
     */
    public function deleteComment(int $commentId): void;
}
