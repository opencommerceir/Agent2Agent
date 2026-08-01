<?php

namespace App\Core\Infrastructure\Logging;

use App\Core\Application\Services\PerformanceMonitor;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * DB::listen()'s own callback target (wired in CoreServiceProvider::boot(),
 * skipped when $this->app->runningUnitTests() — every one of this app's
 * 600+ tests runs real queries, and instrumenting every single one would
 * both slow the suite down and pollute PerformanceMonitor's own rolling
 * window with test-run noise no operator ever wants to see. Exercised
 * directly instead, by constructing an instance and invoking it with a
 * real QueryExecuted event — see QueryLoggerTest.
 *
 * Feeds every query's timing into PerformanceMonitor::recordQueryTime()
 * (which itself only keeps the slow ones, §100ms+) and additionally logs
 * a warning line for anything at or above that same threshold — the
 * Dashboard's own Performance page reads the former, an operator's log
 * aggregator reads the latter.
 */
final class QueryLogger
{
    private const SLOW_QUERY_THRESHOLD_MS = 100.0;

    public function __construct(
        private readonly PerformanceMonitor $monitor,
    ) {
    }

    public function __invoke(QueryExecuted $event): void
    {
        $this->monitor->recordQueryTime($event->time, $event->sql);

        if ($event->time >= self::SLOW_QUERY_THRESHOLD_MS) {
            Log::warning('Slow query detected', [
                'sql' => $event->sql,
                'time_ms' => $event->time,
                'connection' => $event->connectionName,
            ]);
        }
    }
}
