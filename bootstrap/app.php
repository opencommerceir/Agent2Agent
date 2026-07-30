<?php

use App\Core\Exceptions\MCPExceptionHandler;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Scoped to mcp/* only (returning null falls through to Laravel's
        // default handling) — every other route, present or future, is
        // untouched by this.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (MCPExceptionHandler::handles($request)) {
                return (new MCPExceptionHandler())->render($e, $request);
            }

            return null;
        });
    })->create();
