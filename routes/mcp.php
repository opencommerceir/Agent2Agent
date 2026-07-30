<?php

use App\Core\Interfaces\HTTP\Controllers\MCP\MCPDiscoveryController;
use App\Core\Interfaces\HTTP\Controllers\MCP\MCPGatewayController;
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
*/

Route::prefix('mcp/v1')->group(function () {
    Route::get('capabilities', [MCPDiscoveryController::class, 'index']);
    Route::post('execute', [MCPGatewayController::class, 'execute']);
});
