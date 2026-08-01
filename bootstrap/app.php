<?php

use App\Core\Exceptions\MCPExceptionHandler;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RedirectIfAuthenticated;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Scoped to mcp/* only (returning null falls through to Laravel's
        // default handling) — every other route, present or future, is
        // untouched by this.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (MCPExceptionHandler::handles($request)) {
                return app(MCPExceptionHandler::class)->render($e, $request);
            }

            return null;
        });
    })->create();
