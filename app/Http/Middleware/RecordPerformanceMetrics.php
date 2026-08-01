<?php

namespace App\Http\Middleware;

use App\Core\Application\Services\PerformanceMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered globally (bootstrap/app.php) — every request, `web` and
 * `mcp/*` alike, gets its wall-clock duration recorded via
 * PerformanceMonitor::recordRequestTime(). Purely observational: never
 * touches the request or response body/headers, so it can't break
 * anything the way CompressResponse's own docblock explains
 * gzip-encoding the body could.
 */
final class RecordPerformanceMetrics
{
    public function __construct(
        private readonly PerformanceMonitor $monitor,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $this->monitor->recordRequestTime((microtime(true) - $startedAt) * 1000);

        return $response;
    }
}
