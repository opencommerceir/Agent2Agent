<?php

namespace App\Domains\Nexus\Analytics\Interfaces\Http\Controllers;

use App\Domains\Nexus\Analytics\Application\Actions\AssessDealRiskAction;
use App\Domains\Nexus\Analytics\Application\Actions\ForecastSupplierReliabilityAction;
use App\Domains\Nexus\Analytics\Application\Actions\SimulateNegotiationScenarioAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal Predictive Intelligence page (Phase 8/M5) — forecast
 * always loads for a target Business (default: the caller's own, same
 * "own-by-default, overridable" shape MarketIntelligenceController already
 * established); risk/scenario are opt-in via query params (a specific
 * counterparty must already be in mind), same reasoning
 * RecommendationsController's own alternatives/timing sections follow.
 */
class PredictiveIntelligenceController extends Controller
{
    public function __construct(
        private readonly ForecastSupplierReliabilityAction $forecastSupplierReliability,
        private readonly AssessDealRiskAction $assessDealRisk,
        private readonly SimulateNegotiationScenarioAction $simulateNegotiationScenario,
    ) {
    }

    public function index(Request $request): View
    {
        $businessId = $this->actingBusinessId();
        $forecastForId = $request->integer('forecast_for') ?: $businessId;

        $riskForId = $request->integer('risk_for') ?: null;
        $dealAmount = $request->integer('deal_amount') ?: null;
        $dealCurrency = $request->string('deal_currency')->toString() ?: 'IRT';

        $scenarioForId = $request->integer('scenario_for') ?: null;
        $hypotheticalAmount = $request->integer('hypothetical_amount') ?: null;
        $catalogItemType = $request->string('catalog_item_type')->toString() ?: 'product';

        return view('nexus::analytics.predictive', [
            'forecast' => $this->forecastSupplierReliability->execute($forecastForId),
            'riskForId' => $riskForId,
            'risk' => ($riskForId && $dealAmount)
                ? $this->assessDealRisk->execute($businessId, $riskForId, $dealAmount, $dealCurrency)
                : null,
            'scenarioForId' => $scenarioForId,
            'scenario' => ($scenarioForId && $hypotheticalAmount)
                ? $this->simulateNegotiationScenario->execute($businessId, $scenarioForId, CatalogItemType::from($catalogItemType), $hypotheticalAmount)
                : null,
        ]);
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}
