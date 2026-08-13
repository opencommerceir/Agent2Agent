<?php

namespace App\Domains\Nexus\Developer\Interfaces\Http\Controllers;

use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Public, unauthenticated API reference (Phase 9/M4) — no auto-generated
 * OpenAPI/Scribe tooling exists anywhere in this codebase (checked before
 * writing this), and adding one is a bigger dependency than a five-
 * endpoint, three-webhook-event surface needs; same "no new JS/tooling
 * dependency where a hand-rolled page does the job" restraint Network
 * Visualization (Phase 5/M4) already applied. `$endpoints` below is the
 * one place that must be kept in sync by hand when routes/nexus/api.php
 * changes — everything else on the page (scopes, webhook events, rate
 * limit, error envelope) is read live from the same enums/config the
 * actual implementation uses, so it can never drift from reality.
 */
class ApiDocsController extends Controller
{
    /**
     * @var list<array{method: string, path: string, scope: string, description: string}>
     */
    private const ENDPOINTS = [
        ['method' => 'GET', 'path' => '/nexus/api/v1/business', 'scope' => 'business.read', 'description' => 'Your business profile, agent status, catalog counts, credit balance, active negotiations, and reputation score.'],
        ['method' => 'GET', 'path' => '/nexus/api/v1/catalog', 'scope' => 'catalog.read', 'description' => 'Your own products and services. Optional ?query= filters by name.'],
        ['method' => 'GET', 'path' => '/nexus/api/v1/marketplace/search', 'scope' => 'marketplace.read', 'description' => 'Search other verified businesses. Optional ?query= and ?industry=. Costs credits (nexus.marketplace.search).'],
        ['method' => 'GET', 'path' => '/nexus/api/v1/negotiations/{id}', 'scope' => 'negotiation.read', 'description' => 'A negotiation you are a party to.'],
        ['method' => 'GET', 'path' => '/nexus/api/v1/credit/balance', 'scope' => 'credit.read', 'description' => 'Your current credit balance.'],
    ];

    public function index(): View
    {
        return view('nexus::developer.docs.index', [
            'endpoints' => self::ENDPOINTS,
            'scopes' => ApiKeyScope::cases(),
            'webhookEvents' => WebhookEvent::cases(),
            'rateLimitPerMinute' => config('nexus.platform.api.rate_limit_per_minute'),
        ]);
    }
}
