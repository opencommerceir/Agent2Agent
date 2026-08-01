<?php

namespace Tests\Feature\Core;

use App\Core\Application\Services\PerformanceMonitor;
use Tests\TestCase;

/**
 * Backed by whatever cache store is configured (config('cache.default'),
 * `array` under CACHE_STORE=array in phpunit.xml) — needs a booted
 * container for Cache::, the same reason DeprecationNotifierTest (§7.19)
 * is a Feature test rather than a plain PHPUnit one. Every test calls
 * reset() first: nothing clears PerformanceMonitor's own cache keys
 * between tests otherwise (RefreshDatabase only resets the database, not
 * the cache store), so a prior test's samples would otherwise leak in.
 */
class PerformanceMonitorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PerformanceMonitor::class)->reset();
    }

    public function test_recordRequestTime_thenGetAverageResponseTime_returnsTheMean(): void
    {
        $monitor = app(PerformanceMonitor::class);

        $monitor->recordRequestTime(100.0);
        $monitor->recordRequestTime(200.0);
        $monitor->recordRequestTime(300.0);

        $this->assertSame(200.0, $monitor->getAverageResponseTime());
        $this->assertSame(3, $monitor->getRequestCount());
    }

    public function test_getAverageResponseTime_withNoSamples_returnsZero(): void
    {
        $this->assertSame(0.0, app(PerformanceMonitor::class)->getAverageResponseTime());
    }

    public function test_recordQueryTime_belowThreshold_isNotKeptAsASlowQuery(): void
    {
        $monitor = app(PerformanceMonitor::class);

        $monitor->recordQueryTime(50.0, 'select 1');

        $this->assertSame([], $monitor->getSlowQueries());
    }

    public function test_recordQueryTime_atOrAboveThreshold_isKeptAsASlowQuery(): void
    {
        $monitor = app(PerformanceMonitor::class);

        $monitor->recordQueryTime(150.0, 'select * from orders');

        $slowQueries = $monitor->getSlowQueries();

        $this->assertCount(1, $slowQueries);
        $this->assertSame('select * from orders', $slowQueries[0]['query']);
    }

    public function test_getSlowQueries_returnsMostRecentFirst(): void
    {
        $monitor = app(PerformanceMonitor::class);

        $monitor->recordQueryTime(150.0, 'query one');
        $monitor->recordQueryTime(200.0, 'query two');

        $slowQueries = $monitor->getSlowQueries();

        $this->assertSame('query two', $slowQueries[0]['query']);
        $this->assertSame('query one', $slowQueries[1]['query']);
    }

    public function test_recordCacheHitAndMiss_thenGetCacheHitRate_computesThePercentage(): void
    {
        $monitor = app(PerformanceMonitor::class);

        $monitor->recordCacheHit('some:key');
        $monitor->recordCacheHit('some:key');
        $monitor->recordCacheHit('some:key');
        $monitor->recordCacheMiss('some:key');

        $this->assertSame(75.0, $monitor->getCacheHitRate());
    }

    public function test_getCacheHitRate_withNoSamples_returnsZero(): void
    {
        $this->assertSame(0.0, app(PerformanceMonitor::class)->getCacheHitRate());
    }
}
