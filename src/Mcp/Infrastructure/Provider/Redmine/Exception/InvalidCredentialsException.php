<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Exception;

/**
 * Exception thrown when Redmine API credentials are invalid.
 */
final class InvalidCredentialsException extends RedmineApiException
{
    public function __construct(string $message = 'Invalid Redmine API key. Please update your API key in settings.', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
