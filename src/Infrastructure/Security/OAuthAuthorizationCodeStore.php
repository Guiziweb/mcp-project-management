<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Stores OAuth authorization codes with expiration.
 * Uses Symfony Cache for production-ready storage.
 */
final class OAuthAuthorizationCodeStore
{
    private const TTL = 600; // 10 minutes

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Store an authorization code with associated data.
     *
     * @param array{user_id: string, client_id: string, redirect_uri: string, provider: string, provider_url: string, provider_api_key: string, provider_email?: string} $data
     */
    public function store(string $code, array $data): void
    {
        $item = $this->cache->getItem($this->getCacheKey($code));
        $item->set($data);
        $item->expiresAfter(self::TTL);
        $this->cache->save($item);
    }

    /**
     * Retrieve and delete (one-time use) an authorization code.
     *
     * @return array{user_id: string, client_id: string, redirect_uri: string, provider: string, provider_url: string, provider_api_key: string, provider_email?: string}|null
     */
    public function consumeOnce(string $code): ?array
    {
        $key = $this->getCacheKey($code);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();

        // Delete immediately (one-time use)
        $this->cache->deleteItem($key);

        return $data;
    }

    /**
     * Check if a code exists and is not expired.
     */
    public function exists(string $code): bool
    {
        return $this->cache->hasItem($this->getCacheKey($code));
    }

    private function getCacheKey(string $code): string
    {
        return 'oauth_code_'.hash('sha256', $code);
    }
}
