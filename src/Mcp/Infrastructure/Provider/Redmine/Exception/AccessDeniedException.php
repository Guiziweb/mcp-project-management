<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Exception;

/**
 * Exception thrown when access to a Redmine resource is denied.
 */
final class AccessDeniedException extends RedmineApiException
{
    public function __construct(string $message = 'Access denied. You do not have permission to access this resource.', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
