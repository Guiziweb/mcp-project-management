<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Exception;

/**
 * Exception thrown when a Redmine resource is not found.
 */
final class NotFoundException extends RedmineApiException
{
    public function __construct(string $message = 'Resource not found.', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
