<?php

use App\Modules\AgentOrchestrator\Infrastructure\Controllers\AgentController;
use App\Modules\AgentOrchestrator\Infrastructure\Controllers\AgentMemoryController;
use App\Modules\AgentOrchestrator\Infrastructure\Controllers\AgentProfileController;
use App\Modules\AgentOrchestrator\Infrastructure\Controllers\AgentReasoningController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent Orchestrator Routes
|--------------------------------------------------------------------------
|
| Loaded by AgentOrchestratorServiceProvider::boot() via loadRoutesFrom(),
| the same "a module owns and loads its own routes" shape routes/mcp.php
| itself uses (loaded by CoreServiceProvider::boot()) — independent of
| bootstrap/app.php's web/api split. Stateless JSON endpoints for AI
| Agents, not browser sessions, so they carry none of the 'web' middleware
| group; Agent authentication/rate-limiting/permission checks all happen
| per-request inside AgentController, mirroring how routes/mcp.php's own
| controllers authenticate an Agent without a route middleware.
|
| One parametrized route (`{agentType}`, constrained to the 4 supported
| values) rather than 4 literal routes each pointing at the same
| controller method — same URLs (`/api/agents/ceo`, `/api/agents/sales`,
| ...) the module's own request specified, without repeating one line
| four times.
|
*/

Route::prefix('api/agents')->group(function () {
    Route::post('/{agentType}', [AgentController::class, 'execute'])
        ->where('agentType', 'ceo|sales|support|finance');

    Route::get('/executions', [AgentController::class, 'listExecutions']);
    Route::get('/executions/{execution}', [AgentController::class, 'getExecution'])
        ->where('execution', '[0-9]+');

    // Agent persona profiles (§7.27) — a separate Controller
    // (AgentProfileController), the same "Gateway vs. Discovery" split
    // MCPGatewayController/MCPDiscoveryController already establish.
    // Deliberately no {agentType} where() constraint here (unlike the
    // POST route above) — an unknown type should reach
    // AgentProfileNotFoundException's own informative message, not a bare
    // route-level 404 with no body.
    Route::get('/profiles', [AgentProfileController::class, 'index']);
    Route::get('/profiles/{agentType}', [AgentProfileController::class, 'show']);

    // Execution Memory & Learning (§7.29) — insights/suggest only; a
    // "/memory/history" route was deliberately not added, see
    // AgentMemoryController's own docblock (functionally identical to the
    // already-existing GET /executions above).
    Route::get('/memory/insights', [AgentMemoryController::class, 'insights']);
    Route::post('/memory/suggest', [AgentMemoryController::class, 'suggest']);

    // Self-Reflection & Reasoning (§7.31) — both read-only, execution_id
    // as a query param on GET, the same shape /memory/insights already uses.
    Route::get('/reasoning/trace', [AgentReasoningController::class, 'trace']);
    Route::get('/reasoning/explain', [AgentReasoningController::class, 'explain']);
});
