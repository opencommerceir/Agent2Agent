<?php

namespace App\Core\Interfaces\HTTP\Middleware;

use App\Core\Application\Services\DeprecationNotifier;
use App\Core\Application\Services\VersionDetector;
use App\Core\Domain\ValueObjects\ApiVersion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every mcp/* route (both v1 and v2 groups, routes/mcp.php) —
 * the first real middleware ever attached to this route file. Deliberately
 * not the same shape as the Tech Debt Sprint's own per-agent rate limiting
 * (EnforceRateLimitAction, §7.13, an explicit Action call, not middleware)
 * — that decision was specifically because rate limiting needs the Agent's
 * own id, not resolved until AgentAuthenticationService runs inside the
 * controller. Version detection only ever needs the raw Request (URL
 * path/Accept header/query string), which a middleware already has before
 * the controller runs — no conflict with that earlier precedent, just a
 * different-shaped cross-cutting concern landing on the tool that already
 * fits it.
 *
 * Lives under Interfaces/HTTP, not Infrastructure — middleware is an
 * HTTP-adapter concern, the same reasoning every other class already
 * under Interfaces/HTTP/Controllers/MCP has, not a persistence adapter.
 *
 * Always attaches X-API-Version. Additionally attaches
 * Deprecation/Sunset/Link/Warning and logs one warning line only when
 * DeprecationNotifierInterface says the detected version is actually
 * deprecated (config('api.deprecation'), config/api.php) — a version with
 * no entry there (v2, today) gets none of this. agent_id in the log line
 * comes from $request->attributes, set by
 * AbstractMCPGatewayController/MCPDiscoveryController right after
 * authentication succeeds — null for a request that failed authentication
 * before ever reaching that point, which is not an error, just means no
 * Agent identity was ever established for this particular deprecated hit.
 */
final class ApiVersioning
{
    public function __construct(
        private readonly VersionDetector $detector,
        private readonly DeprecationNotifier $deprecation,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $version = $this->detector->detectFromRequest($request);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(config('api.headers.version'), $version->value);

        if ($this->deprecation->isDeprecated($version)) {
            $this->attachDeprecationHeaders($response, $version);
            $this->logDeprecatedUsage($request, $version);
        }

        return $response;
    }

    private function attachDeprecationHeaders(Response $response, ApiVersion $version): void
    {
        $sunset = $this->deprecation->sunsetDateFor($version);
        $migrationGuide = $this->deprecation->migrationGuideUrlFor($version);

        $response->headers->set(config('api.headers.deprecation'), 'true');

        if ($sunset !== null) {
            $response->headers->set(config('api.headers.sunset'), $sunset->toHttpDate());
        }

        if ($migrationGuide !== null) {
            $response->headers->set(config('api.headers.link'), "<{$migrationGuide}>; rel=\"successor-version\"");
        }

        $warning = $this->deprecation->warningMessageFor($version);

        if ($warning !== null) {
            $response->headers->set(config('api.headers.warning'), '299 - "'.$warning.'"');
        }
    }

    private function logDeprecatedUsage(Request $request, ApiVersion $version): void
    {
        Log::warning('Deprecated API version used', [
            'version' => $version->value,
            'agent_id' => $request->attributes->get('agent_id'),
            'endpoint' => $request->path(),
        ]);
    }
}
