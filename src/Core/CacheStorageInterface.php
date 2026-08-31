<?php
declare(strict_types=1);

namespace foun10\EasyCache\Core;

/**
 * Contract for a full-page cache backend. Tags are opaque strings
 * (e.g. "product-123", "category-45") describing which entities a
 * cached entry's HTML depends on - how a given implementation tracks
 * and invalidates them is entirely up to that implementation.
 * EasyCache/ShopControl only ever call set()/invalidateTags(), never
 * anything backend-specific, so swapping FileCacheStorage for another
 * implementation (e.g. a Memcache-backed one) needs no caller changes.
 */
interface CacheStorageInterface
{
    public function get(string $key, bool $useGzip = false): ?string;

    /**
     * @param string[] $tags
     */
    public function set(string $key, string $body, int $expiresAt, bool $useGzip = false, array $tags = []): void;

    /**
     * Invalidates every cache entry written with any of the given tags.
     *
     * @param string[] $tags
     * @return int number of cache entries invalidated
     */
    public function invalidateTags(array $tags): int;

    /**
     * @return int number of cache files removed
     */
    public function clear(): int;

    /**
     * @return array{count: int, sizeBytes: int}
     */
    public function getStats(): array;
}
