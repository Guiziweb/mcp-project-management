<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Exception;

/**
 * Base exception for Redmine API errors.
 */
class RedmineApiException extends \RuntimeException
{
    public function __construct(string $message = 'Redmine API error.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
