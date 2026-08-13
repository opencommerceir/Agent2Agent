<?php

namespace App\Domains\Nexus\Analytics\Interfaces\Http\Controllers;

use App\Domains\Nexus\Analytics\Application\Actions\GetMarketIntelligenceAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal Market Intelligence page (Phase 8/M2) — a separate
 * controller from BusinessAnalyticsController (own numbers) rather than
 * one growing controller, same "one controller per portal feature" shape
 * Growth's ReferralController/InviteController/CoalitionController already
 * established.
 */
class MarketIntelligenceController extends Controller
{
    public function __construct(
        private readonly GetMarketIntelligenceAction $getMarketIntelligence,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return view('nexus::analytics.market', [
            'market' => $this->getMarketIntelligence->execute($owner->business_id, $request->string('industry')->value() ?: null),
        ]);
    }
}
