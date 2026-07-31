<?php

namespace App\Core\Application\Actions;

use App\Core\Domain\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-agent rate limiting for the MCP Gateway. No middleware layer exists
 * on mcp/* routes today — AgentAuthenticationService resolves the Agent
 * (and its id) inside MCPGatewayController itself, not via a Guard or
 * route middleware (that class's own docblock already explains why: no
 * framework magic, explicit over hidden behavior). Building a route-level
 * `throttle:` middleware here would mean re-parsing the bearer token a
 * second time just to get an id to key on. Simpler and consistent with
 * this codebase's existing style: enforce the limit as one more explicit
 * Action call, right after the Agent is resolved, using Laravel's
 * RateLimiter facade directly — no RateLimiter::for()/middleware
 * registration needed, since there's no middleware pipeline to hook it
 * into.
 *
 * Mirrors CheckInventoryAction/CheckPermissionAction's own shape:
 * execute() is the primary query (bool), authorize() is a throw-on-deny
 * convenience wrapper.
 */
final class EnforceRateLimitAction
{
    public function execute(int $agentId): bool
    {
        return ! RateLimiter::tooManyAttempts($this->key($agentId), $this->maxAttemptsPerMinute());
    }

    public function authorize(int $agentId): void
    {
        $key = $this->key($agentId);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttemptsPerMinute())) {
            throw new RateLimitExceededException(
                "Rate limit exceeded for agent [{$agentId}]: max {$this->maxAttemptsPerMinute()} requests per minute."
            );
        }

        RateLimiter::hit($key, 60);
    }

    private function key(int $agentId): string
    {
        return "mcp-agent:{$agentId}";
    }

    private function maxAttemptsPerMinute(): int
    {
        return (int) config('mcp.rate_limit_per_minute', 100);
    }
}
