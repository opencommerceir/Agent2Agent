<?php

namespace Tests\Feature\Core;

use App\Core\Application\Services\CacheService;
use App\Core\Application\Services\PerformanceMonitor;
use Tests\TestCase;

/**
 * CACHE_STORE=array in phpunit.xml — confirmed (Stage 8 planning) that
 * Laravel's ArrayStore extends TaggableStore, so the tagging assertions
 * below are exercising real behavior, not a store-specific quirk that
 * would only work under Redis.
 */
class CacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PerformanceMonitor::class)->reset();
    }

    public function test_get_onFirstCall_isACacheMissAndComputesTheValue(): void
    {
        $cache = app(CacheService::class);

        $value = $cache->get('test:key:v1', fn () => 'computed-value', 3600);

        $this->assertSame('computed-value', $value);
        $this->assertSame(0.0, app(PerformanceMonitor::class)->getCacheHitRate());
    }

    public function test_get_onSecondCall_isACacheHitAndDoesNotRecompute(): void
    {
        $cache = app(CacheService::class);
        $calls = 0;

        $cache->get('test:key:v1', function () use (&$calls) {
            $calls++;

            return 'computed-value';
        }, 3600);

        $value = $cache->get('test:key:v1', function () use (&$calls) {
            $calls++;

            return 'computed-value';
        }, 3600);

        $this->assertSame('computed-value', $value);
        $this->assertSame(1, $calls);
        $this->assertSame(50.0, app(PerformanceMonitor::class)->getCacheHitRate());
    }

    public function test_forget_removesTheKeySoTheNextGetRecomputes(): void
    {
        $cache = app(CacheService::class);
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'value-'.$calls;
        };

        $cache->get('test:forget:v1', $compute, 3600);
        $cache->forget('test:forget:v1');
        $value = $cache->get('test:forget:v1', $compute, 3600);

        $this->assertSame('value-2', $value);
        $this->assertSame(2, $calls);
    }

    public function test_flush_removesEveryEntryStoredUnderThatTag(): void
    {
        $cache = app(CacheService::class);
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return "value-{$calls}";
        };

        $cache->get('test:tagged:1:v1', $compute, 3600, ['entity:widget']);
        $cache->get('test:tagged:2:v1', $compute, 3600, ['entity:widget']);

        $cache->flush('entity:widget');

        $value = $cache->get('test:tagged:1:v1', $compute, 3600, ['entity:widget']);

        $this->assertSame('value-3', $value);
    }

    public function test_flush_doesNotAffectEntriesUnderADifferentTag(): void
    {
        $cache = app(CacheService::class);

        $cache->get('test:tagged:a:v1', fn () => 'a-value', 3600, ['entity:widget']);
        $cache->get('test:tagged:b:v1', fn () => 'b-value', 3600, ['entity:gadget']);

        $cache->flush('entity:widget');

        $stillCached = $cache->get('test:tagged:b:v1', fn () => 'recomputed', 3600, ['entity:gadget']);

        $this->assertSame('b-value', $stillCached);
    }
}
