<?php

namespace App\Core\Application\Services;

use Illuminate\Support\Facades\Cache;

/**
 * The one cache entry point every module should call through — never
 * `Cache::` directly from inside a Domain Module's own Action (the same
 * "one shared, thin Application-layer wrapper instead of every caller
 * reaching for a framework facade on its own" reasoning
 * EnforceRateLimitAction/LanguageDetector already establish for their own
 * concerns).
 *
 * Key format convention (documented, not structurally enforced — a plain
 * string key works with any Laravel cache store regardless of shape):
 * `{module}:{entity}:{id}:{version}`, e.g. `commerce:product:123:v1`,
 * `commerce:product:list:tenant_5:page_1:v1`,
 * `analytics:kpi:revenue:monthly:tenant_5:v1`. The trailing `:v1` exists
 * so a future change to what a cached payload's own shape looks like can
 * bump to `:v2` without needing to flush every existing key by hand — an
 * old `:v1` entry simply expires on its own TTL and is never read again.
 *
 * `$tags` (Laravel's own Cache::tags(), Redis/array/database/memcached
 * all support it — confirmed this app's own testing config, CACHE_STORE=
 * array, does too) is an optional trailing parameter (HANDOFF §3 pattern
 * #6) rather than a required one, since `get()`'s signature was requested
 * as `get(string $key, callable $callback, int $ttl = 3600)` with no tags
 * parameter at all — every caller that doesn't need group-invalidation
 * simply omits it and gets a plain, untagged cache entry that still works
 * on every store.
 */
final class CacheService
{
    public function __construct(
        private readonly PerformanceMonitor $monitor,
    ) {
    }

    /**
     * @param list<string> $tags
     */
    public function get(string $key, callable $callback, int $ttl = 3600, array $tags = []): mixed
    {
        $store = $tags === [] ? Cache::store() : Cache::tags($tags);

        $existed = $store->has($key);
        $value = $store->remember($key, $ttl, $callback);

        $existed ? $this->monitor->recordCacheHit($key) : $this->monitor->recordCacheMiss($key);

        return $value;
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Flushes every entry stored under a given tag — `tenant:{id}`,
     * `module:{name}`, or `entity:{type}` per this class's own docblock.
     * Named `flush()` per the request's own signature
     * (`flush(string $prefix): void`); the parameter is really a tag name,
     * not a literal key-string prefix, since Laravel has no native
     * "delete every key starting with X" primitive on most stores —
     * tags are the real, portable mechanism for group invalidation.
     */
    public function flush(string $tag): void
    {
        Cache::tags([$tag])->flush();
    }
}
