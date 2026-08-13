<?php

namespace App\Domains\Nexus\Marketplace\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Marketplace\Application\Actions\GetRecommendationsAction;
use App\Domains\Nexus\Marketplace\Application\Actions\RecommendAlternativeSuppliersAction;
use App\Domains\Nexus\Marketplace\Application\Actions\RecommendNegotiationTimingAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal AI Recommendations page (Phase 8/M3) — the human-operated
 * counterpart to the nexus.marketplace.{recommendations,alternatives,
 * negotiation_timing} MCP capabilities an Agent can also call directly,
 * same shape CoalitionController already established for its own MCP
 * capability pair. Same-industry recommendations always load;
 * alternatives/timing are opt-in via query params (a specific supplier
 * must already be in mind) rather than three separate pages.
 */
class RecommendationsController extends Controller
{
    public function __construct(
        private readonly GetRecommendationsAction $getRecommendations,
        private readonly RecommendAlternativeSuppliersAction $recommendAlternativeSuppliers,
        private readonly RecommendNegotiationTimingAction $recommendNegotiationTiming,
    ) {
    }

    public function index(Request $request): View
    {
        $businessId = $this->actingBusinessId();

        $alternativeToId = $request->integer('alternative_to') ?: null;
        $timingForId = $request->integer('timing_for') ?: null;

        return view('nexus::marketplace.recommendations', [
            'recommendations' => $this->getRecommendations->execute($businessId),
            'alternativeToId' => $alternativeToId,
            'alternatives' => $alternativeToId ? $this->recommendAlternativeSuppliers->execute($businessId, $alternativeToId) : null,
            'timingForId' => $timingForId,
            'timing' => $timingForId ? $this->recommendNegotiationTiming->execute($businessId, $timingForId) : null,
        ]);
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}
