<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Security;

/**
 * Validates OAuth redirect URIs against a whitelist.
 *
 * MCP clients (Claude Desktop, Cursor, etc.) typically run locally,
 * so we restrict redirects to localhost and known client schemes.
 */
final class RedirectUriValidator
{
    /**
     * @var array<string>
     */
    private const DEFAULT_PATTERNS = [
        '#^https?://(localhost|127\\.0\\.0\\.1)(:\\d+)?(/.*)?$#',
        '#^cursor://anysphere\\.cursor-mcp(/.*)?$#',
        '#^https://claude\\.ai/api/mcp/auth_callback$#',
    ];

    /**
     * @param array<string> $additionalPatterns Additional regex patterns to allow
     */
    public function __construct(
        private readonly array $additionalPatterns = [],
    ) {
    }

    /**
     * Check if a redirect URI is allowed.
     */
    public function isAllowed(string $redirectUri): bool
    {
        $patterns = array_merge(self::DEFAULT_PATTERNS, $this->additionalPatterns);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $redirectUri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all allowed patterns (for debugging/admin display).
     *
     * @return array<string>
     */
    public function getAllowedPatterns(): array
    {
        return array_merge(self::DEFAULT_PATTERNS, $this->additionalPatterns);
    }
}
