<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered globally (bootstrap/app.php). Real static assets
 * (`public/build/*` under `@vite()`) are served directly by the web
 * server in any real deployment and never reach this middleware at all —
 * this only matters for a request that genuinely goes through the
 * Laravel/PHP process, e.g. `php artisan serve` in local development, or
 * a reverse proxy explicitly configured to route asset paths through PHP.
 * Documented honestly rather than overstated: in a standard nginx/Vite
 * production setup, this class's `isStaticAsset()` branch is close to a
 * no-op, and the real long-cache behavior for hashed build assets comes
 * from the web server's own config instead.
 *
 * `mcp/*` responses get `no-cache/no-store` — an MCP response is always
 * per-Agent, per-request data (an Order, a KPI value, ...), never a safe
 * shared-cache candidate.
 */
final class SetCDNHeaders
{
    private const STATIC_ASSET_EXTENSIONS = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ico'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isStaticAsset($request)) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
            $response->headers->set('CDN-Cache-Control', 'max-age=31536000');
        } elseif ($this->isApiResponse($request)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function isStaticAsset(Request $request): bool
    {
        $extension = strtolower(pathinfo($request->path(), PATHINFO_EXTENSION));

        return in_array($extension, self::STATIC_ASSET_EXTENSIONS, true);
    }

    private function isApiResponse(Request $request): bool
    {
        return $request->is('mcp/*');
    }
}
