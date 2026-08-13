<?php

use App\Core\Interfaces\HTTP\Middleware\ApiVersioning;
use App\Domains\Nexus\Analytics\Interfaces\Http\Controllers\Api\BusinessProfileApiController;
use App\Domains\Nexus\Catalog\Interfaces\Http\Controllers\Api\CatalogApiController;
use App\Domains\Nexus\Credit\Interfaces\Http\Controllers\Api\CreditApiController;
use App\Domains\Nexus\Developer\Interfaces\Http\Controllers\GraphQLController;
use App\Domains\Nexus\Developer\Interfaces\Http\Middleware\EnsureValidApiKey;
use App\Domains\Nexus\Marketplace\Interfaces\Http\Controllers\Api\MarketplaceApiController;
use App\Domains\Nexus\Negotiation\Interfaces\Http\Controllers\Api\NegotiationApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nexus Public REST API (Phase 9, M2)
|--------------------------------------------------------------------------
|
| Loaded by NexusServiceProvider::boot() via loadRoutesFrom() — stateless
| JSON, no 'web' middleware group (no CSRF/session), same shape
| routes/mcp.php already established for AI Agents. This surface is for
| third-party developer software instead: authenticated by an ApiKey
| (Phase 9/M1) rather than an AgentToken, and every handler is a thin
| controller wrapping the exact same Action an equivalent MCP capability
| already calls — no business logic is duplicated for this channel.
|
| ApiVersioning (reused unchanged from routes/mcp.php) attaches
| X-API-Version and applies to the whole group regardless of per-route
| scope. EnsureValidApiKey is attached per-route (not at the group level)
| specifically so each route can require its own scope
| (EnsureValidApiKey::class.':catalog.read', etc.) — a single group-level
| instance with no scope parameter would authenticate correctly but never
| enforce which scope a key actually needs for a given endpoint.
| 'throttle:nexus-api' (registered in NexusServiceProvider::boot()) is
| listed after EnsureValidApiKey in each route's own middleware array so
| the limiter can key by the now-resolved ApiKey id instead of IP.
|
*/

Route::middleware(ApiVersioning::class)->prefix('nexus/api/v1')->group(function () {
    Route::middleware([EnsureValidApiKey::class.':business.read', 'throttle:nexus-api'])
        ->get('business', [BusinessProfileApiController::class, 'show']);

    Route::middleware([EnsureValidApiKey::class.':catalog.read', 'throttle:nexus-api'])
        ->get('catalog', [CatalogApiController::class, 'index']);

    Route::middleware([EnsureValidApiKey::class.':marketplace.read', 'throttle:nexus-api'])
        ->get('marketplace/search', [MarketplaceApiController::class, 'search']);

    Route::middleware([EnsureValidApiKey::class.':negotiation.read', 'throttle:nexus-api'])
        ->get('negotiations/{negotiation}', [NegotiationApiController::class, 'show']);

    Route::middleware([EnsureValidApiKey::class.':credit.read', 'throttle:nexus-api'])
        ->get('credit/balance', [CreditApiController::class, 'show']);

    // GraphQL (Phase 9, M5) — no scope parameter on EnsureValidApiKey here;
    // scope is enforced per-field inside PublicApiSchemaFactory since a
    // single GraphQL request can touch multiple resources at once.
    Route::middleware([EnsureValidApiKey::class, 'throttle:nexus-api'])
        ->post('graphql', [GraphQLController::class, 'execute']);
});
