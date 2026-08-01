<?php

namespace App\Core\Application\Services;

use Illuminate\Support\Facades\Cache;

/**
 * A lightweight, in-process operational monitor — not a substitute for a
 * real APM (New Relic/Datadog/etc.) in production, and deliberately not
 * one: this stores a capped rolling window of samples in whatever cache
 * store is configured (config('cache.default')), read-modify-write, no
 * cross-request locking. That's an honest, documented trade-off (the same
 * "real, working, honestly-scoped-down" shape Analytics'
 * CustomerRetentionRate/CustomerLifetimeValue simplifications already
 * established, §7.18) — good enough to answer "is this app roughly
 * healthy right now" on the Dashboard's own Performance page, not
 * precise enough to bill anyone by or alert on-call from.
 *
 * Every value stored here survives a `Cache::flush()` no differently than
 * any other cache entry — restarting the cache store resets these
 * counters to zero, which is fine for a rolling operational snapshot.
 */
final class PerformanceMonitor
{
    private const REQUEST_TIMES_KEY = 'perf:request_times_ms';

    private const SLOW_QUERIES_KEY = 'perf:slow_queries';

    private const CACHE_HITS_KEY = 'perf:cache_hits';

    private const CACHE_MISSES_KEY = 'perf:cache_misses';

    private const MAX_SAMPLES = 200;

    private const SLOW_QUERY_THRESHOLD_MS = 100.0;

    private const SAMPLE_TTL_SECONDS = 86400;

    public function recordRequestTime(float $durationMs): void
    {
        $this->pushCapped(self::REQUEST_TIMES_KEY, $durationMs);
    }

    /**
     * Only durations at or above SLOW_QUERY_THRESHOLD_MS (100ms) are kept
     * — a fast query recorded on every single request would fill the
     * capped window with noise instantly.
     */
    public function recordQueryTime(float $durationMs, string $query): void
    {
        if ($durationMs < self::SLOW_QUERY_THRESHOLD_MS) {
            return;
        }

        $this->pushCapped(self::SLOW_QUERIES_KEY, [
            'query' => $query,
            'time_ms' => round($durationMs, 2),
            'at' => now()->toIso8601String(),
        ]);
    }

    public function recordCacheHit(string $key): void
    {
        Cache::increment(self::CACHE_HITS_KEY);
    }

    public function recordCacheMiss(string $key): void
    {
        Cache::increment(self::CACHE_MISSES_KEY);
    }

    public function getAverageResponseTime(): float
    {
        $samples = Cache::get(self::REQUEST_TIMES_KEY, []);

        return $samples === [] ? 0.0 : round(array_sum($samples) / count($samples), 2);
    }

    /**
     * @return list<array{query: string, time_ms: float, at: string}>
     */
    public function getSlowQueries(int $limit = 10): array
    {
        $queries = Cache::get(self::SLOW_QUERIES_KEY, []);

        return array_slice(array_reverse($queries), 0, $limit);
    }

    public function getCacheHitRate(): float
    {
        $hits = (int) Cache::get(self::CACHE_HITS_KEY, 0);
        $misses = (int) Cache::get(self::CACHE_MISSES_KEY, 0);
        $total = $hits + $misses;

        return $total === 0 ? 0.0 : round(($hits / $total) * 100, 2);
    }

    public function getRequestCount(): int
    {
        return count(Cache::get(self::REQUEST_TIMES_KEY, []));
    }

    /**
     * Test/ops convenience — not called anywhere in request handling
     * itself.
     */
    public function reset(): void
    {
        foreach ([self::REQUEST_TIMES_KEY, self::SLOW_QUERIES_KEY, self::CACHE_HITS_KEY, self::CACHE_MISSES_KEY] as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Not atomic across concurrent requests (read-modify-write) — an
     * acceptable, documented trade-off for a rolling operational
     * snapshot, the same reasoning this class's own docblock gives.
     */
    private function pushCapped(string $key, mixed $value): void
    {
        $samples = Cache::get($key, []);
        $samples[] = $value;

        if (count($samples) > self::MAX_SAMPLES) {
            $samples = array_slice($samples, -self::MAX_SAMPLES);
        }

        Cache::put($key, $samples, self::SAMPLE_TTL_SECONDS);
    }
}
