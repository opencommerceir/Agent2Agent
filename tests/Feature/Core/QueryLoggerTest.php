<?php

namespace Tests\Feature\Core;

use App\Core\Application\Services\PerformanceMonitor;
use App\Core\Infrastructure\Logging\QueryLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Exercised directly — constructing an instance and invoking it with a
 * real QueryExecuted event — rather than relying on the global
 * DB::listen() wiring in CoreServiceProvider::boot(), which is itself
 * skipped during the test suite (that provider's own docblock explains
 * why: instrumenting every real query in 600+ tests would slow the suite
 * and pollute PerformanceMonitor's rolling window with test-run noise).
 */
class QueryLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PerformanceMonitor::class)->reset();
    }

    public function test_invoke_feedsQueryTimingIntoPerformanceMonitor(): void
    {
        $logger = app(QueryLogger::class);
        $event = new QueryExecuted('select * from `orders`', [], 150.0, DB::connection());

        $logger($event);

        $slowQueries = app(PerformanceMonitor::class)->getSlowQueries();

        $this->assertCount(1, $slowQueries);
        $this->assertSame('select * from `orders`', $slowQueries[0]['query']);
    }

    public function test_invoke_belowThreshold_doesNotLogAWarning(): void
    {
        Log::spy();

        $logger = app(QueryLogger::class);
        $event = new QueryExecuted('select 1', [], 5.0, DB::connection());

        $logger($event);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_invoke_atOrAboveThreshold_logsASlowQueryWarning(): void
    {
        Log::spy();

        $logger = app(QueryLogger::class);
        $event = new QueryExecuted('select * from `orders`', [], 150.0, DB::connection());

        $logger($event);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Slow query detected'
                && $context['sql'] === 'select * from `orders`'
                && $context['time_ms'] === 150.0);
    }
}
