<?php

namespace Tests\Unit\Core;

use App\Core\Application\Services\LazyLoadingDetector;
use PHPUnit\Framework\TestCase;

/**
 * Framework-free — analyze() takes a plain array (the shape
 * DB::getQueryLog() returns), never touches DB::* itself. Proves the
 * corrected heuristic (repeated query *shape*, not "fast + SELECT *") —
 * see this class's own docblock for why the request's original proposal
 * would have been unreliable in both directions.
 */
class LazyLoadingDetectorTest extends TestCase
{
    public function test_analyze_withARepeatedShape_flagsItAsSuspectedNPlusOne(): void
    {
        $detector = new LazyLoadingDetector();

        $queries = [
            ['query' => 'select * from `orders` where `tenant_id` = ?'],
            ['query' => 'select * from `order_items` where `order_id` = 1'],
            ['query' => 'select * from `order_items` where `order_id` = 2'],
            ['query' => 'select * from `order_items` where `order_id` = 3'],
        ];

        $suspects = $detector->analyze($queries);

        $this->assertCount(1, $suspects);
        $this->assertSame(3, $suspects[0]['occurrences']);
        $this->assertSame('select * from `order_items` where `order_id` = ?', $suspects[0]['sql']);
    }

    public function test_analyze_withNoRepeatedShape_returnsEmpty(): void
    {
        $detector = new LazyLoadingDetector();

        $queries = [
            ['query' => 'select * from `orders` where `id` = 1'],
            ['query' => 'select * from `products` where `id` = 2'],
            ['query' => 'select * from `customers` where `id` = 3'],
        ];

        $this->assertSame([], $detector->analyze($queries));
    }

    public function test_analyze_withOnlyTwoOccurrences_staysBelowTheThreshold(): void
    {
        $detector = new LazyLoadingDetector();

        $queries = [
            ['query' => 'select * from `order_items` where `order_id` = 1'],
            ['query' => 'select * from `order_items` where `order_id` = 2'],
        ];

        $this->assertSame([], $detector->analyze($queries));
    }

    public function test_analyze_withAFastQueryRepeatedManyTimes_stillFlagsIt(): void
    {
        // The request's own original heuristic ("fast + SELECT *") would
        // have flagged individual fast queries as suspicious regardless of
        // repetition, or missed a genuinely slow N+1 entirely — this
        // proves the corrected version flags purely on repeated shape,
        // independent of how fast any one occurrence was.
        $detector = new LazyLoadingDetector();

        $queries = array_fill(0, 5, ['query' => 'select * from `carts` where `id` = 7']);

        $suspects = $detector->analyze($queries);

        $this->assertCount(1, $suspects);
        $this->assertSame(5, $suspects[0]['occurrences']);
    }

    public function test_analyze_sortsMultipleSuspectsByOccurrenceDescending(): void
    {
        $detector = new LazyLoadingDetector();

        $queries = [
            ...array_fill(0, 3, ['query' => 'select * from `a` where `id` = 1']),
            ...array_fill(0, 6, ['query' => 'select * from `b` where `id` = 1']),
        ];

        $suspects = $detector->analyze($queries);

        $this->assertCount(2, $suspects);
        $this->assertSame(6, $suspects[0]['occurrences']);
        $this->assertSame(3, $suspects[1]['occurrences']);
    }
}
