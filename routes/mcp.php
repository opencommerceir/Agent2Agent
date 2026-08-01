<?php

use App\Core\Interfaces\HTTP\Controllers\MCP\MCPDiscoveryController;
use App\Core\Interfaces\HTTP\Controllers\MCP\MCPDiscoveryControllerV2;
use App\Core\Interfaces\HTTP\Controllers\MCP\MCPGatewayController;
use App\Core\Interfaces\HTTP\Controllers\MCP\MCPGatewayControllerV2;
use App\Core\Interfaces\HTTP\Middleware\ApiVersioning;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MCP Gateway Routes
|--------------------------------------------------------------------------
|
| Loaded by CoreServiceProvider::boot() via loadRoutesFrom(), independent
| of bootstrap/app.php's web/api split — these are stateless JSON
| endpoints for AI Agents, not browser sessions, so they intentionally
| carry none of the 'web' middleware group (no CSRF, no session cookie).
| Agent authentication is handled per-request inside the controllers via
| AgentAuthenticationService, not a route middleware.
|
| ApiVersioning (Stage 7 — API Versioning, the first real middleware ever
| attached here) wraps both version groups: it attaches X-API-Version
| always, plus Deprecation/Sunset/Link/Warning + a log line for whichever
| version config('api.deprecation') actually names (v1 today). Which
| controller class runs is decided by the URL prefix alone, same as
| always — the middleware never changes that, only decorates the
| response afterward and reports what it saw.
|
*/

Route::middleware(ApiVersioning::class)->group(function () {
    Route::prefix('mcp/v1')->group(function () {
        Route::get('capabilities', [MCPDiscoveryController::class, 'index']);
        Route::post('execute', [MCPGatewayController::class, 'execute']);
    });

    Route::prefix('mcp/v2')->group(function () {
        Route::get('capabilities', [MCPDiscoveryControllerV2::class, 'index']);
        Route::post('execute', [MCPGatewayControllerV2::class, 'execute']);
    });
});
