<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Security;

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
     * @param array{user_id: int, client_id: string, redirect_uri: string, provider: string, org_config: array<string, mixed>, user_credentials: array<string, mixed>} $data
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
     * Uses a lock mechanism to prevent race conditions where two concurrent
     * requests could consume the same code before deletion completes.
     *
     * @return array{user_id: int, client_id: string, redirect_uri: string, provider: string, org_config: array<string, mixed>, user_credentials: array<string, mixed>}|null
     */
    public function consumeOnce(string $code): ?array
    {
        $key = $this->getCacheKey($code);
        $lockKey = $key.'_lock';

        // Try to acquire lock (atomic claim)
        $lockItem = $this->cache->getItem($lockKey);
        if ($lockItem->isHit()) {
            // Another request is already consuming this code
            return null;
        }

        // Set lock with short TTL (5 seconds max processing time)
        $lockItem->set(true);
        $lockItem->expiresAfter(5);
        $this->cache->save($lockItem);

        // Now safely get the data
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            // Code doesn't exist or was already consumed
            $this->cache->deleteItem($lockKey);

            return null;
        }

        $data = $item->get();

        // Delete code and lock
        $this->cache->deleteItem($key);
        $this->cache->deleteItem($lockKey);

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
