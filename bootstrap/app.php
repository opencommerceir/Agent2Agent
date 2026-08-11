<?php

use App\Core\Exceptions\MCPExceptionHandler;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CompressResponse;
use App\Http\Middleware\EnsureBusinessOwnerIsAuthenticated;
use App\Http\Middleware\EnsureShowcaseAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RecordPerformanceMetrics;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfBusinessOwnerIsAuthenticated;
use App\Http\Middleware\SetCDNHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Phase 4 Stage 5 (Admin Dashboard + Human Auth) — explicit
        // aliases rather than relying on the framework's own default
        // 'auth'/'guest' middleware classes, so the app's own
        // Authenticate/RedirectIfAuthenticated (redirect to the Dashboard's
        // own named routes) are what actually run.
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'admin' => EnsureUserIsAdmin::class,
            // Showcase prep, Phase 3 (§7.33) — a light session-flag gate
            // for `/showcase/*`, completely independent of the 3 aliases
            // above (no real User/Agent identity behind it).
            'showcase.access' => EnsureShowcaseAccess::class,
            // Nexus Business portal (Phase 1, M2) — same shape as
            // 'auth'/'guest' above, one guard down. Always applied with
            // the ':business' guard parameter (e.g. 'business.auth:business')
            // since the app's default guard stays 'web'.
            'business.auth' => EnsureBusinessOwnerIsAuthenticated::class,
            'business.guest' => RedirectIfBusinessOwnerIsAuthenticated::class,
        ]);

        // Phase 4 Stage 8 (Performance Optimization, §7.20). Global —
        // applies to `web` and `mcp/*` alike, the same way ApiVersioning
        // (Stage 7) proved a route-specific middleware can coexist with a
        // truly global one. RecordPerformanceMetrics/SetCDNHeaders are
        // pure observation/header-only and safe everywhere; CompressResponse
        // is scoped to `web` only — see its own docblock for why applying
        // it to `mcp/*` (or globally) would be unsafe.
        $middleware->append([
            RecordPerformanceMetrics::class,
            SetCDNHeaders::class,
        ]);
        $middleware->web(append: [
            CompressResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Scoped to mcp/* and api/agents/* only (see MCPExceptionHandler::handles(),
        // §7.26) — returning null falls through to Laravel's default
        // handling; every other route, present or future, is untouched.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (MCPExceptionHandler::handles($request)) {
                return app(MCPExceptionHandler::class)->render($e, $request);
            }

            return null;
        });
    })->create();
