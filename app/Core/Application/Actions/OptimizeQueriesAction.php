<?php

namespace App\Core\Application\Actions;

use App\Core\Application\Services\LazyLoadingDetector;
use App\Core\Application\Services\PerformanceMonitor;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic — never called from request handling itself, only
 * from `performance:check-lazy-loading` and the Dashboard's own
 * Performance page. Combines two independent signals: slow queries
 * already captured by QueryLogger over real traffic
 * (PerformanceMonitor::getSlowQueries(), no query log needs to be active
 * for this part), and a repeated-query-shape scan
 * (LazyLoadingDetector::analyze()) over whatever the *caller* already
 * captured via DB::enableQueryLog() before calling execute() — this
 * Action does not enable query logging itself, since doing so here would
 * only ever see its own queries (there are none), not whatever scenario
 * the caller actually wants inspected.
 *
 * "Suggestions" are intentionally simple, human-readable hints, not a
 * static-analysis tool that rewrites Repository code — the request's own
 * ask here ("پیشنهاد eager loading، پیشنهاد indexing") was too open-ended
 * to build a precise recommender against; a repeated query shape is
 * exactly the signal a human reading this output needs to go add a
 * ->with() call or an index, the same way EloquentOrderRepository's own
 * N+1 (§7.20) was found and fixed this stage.
 */
final class OptimizeQueriesAction
{
    public function __construct(
        private readonly PerformanceMonitor $monitor,
        private readonly LazyLoadingDetector $detector,
    ) {
    }

    /**
     * @return array{slow_queries: array, suspected_n_plus_one: array, suggestions: list<string>}
     */
    public function execute(): array
    {
        $suspectedNPlusOne = $this->detector->analyze(DB::getQueryLog());

        return [
            'slow_queries' => $this->monitor->getSlowQueries(20),
            'suspected_n_plus_one' => $suspectedNPlusOne,
            'suggestions' => $this->buildSuggestions($suspectedNPlusOne),
        ];
    }

    /**
     * @param list<array{sql: string, occurrences: int}> $suspects
     * @return list<string>
     */
    private function buildSuggestions(array $suspects): array
    {
        return array_map(
            fn (array $suspect) => "Query shape repeated {$suspect['occurrences']} times — consider eager-loading the relation this reads, or adding an index if it's a WHERE lookup: {$suspect['sql']}",
            $suspects,
        );
    }
}
