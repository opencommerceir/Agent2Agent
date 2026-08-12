<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard) — the roadmap's "Admin Margin
 * Settings" (LLM cost markup, transaction fee, subscription markup,
 * negotiation success fee). Thin — MarginSettingsService itself is
 * already the right Application-layer shape (get/set), so this stays a
 * direct dependency rather than wrapping it in pass-through Actions,
 * same "controller depends on a Service, not everything needs an Action"
 * shape NegotiationReasoningService's own callers already establish.
 */
class NexusMarginSettingsController extends Controller
{
    public function __construct(
        private readonly MarginSettingsService $marginSettings,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.margin-settings.index', [
            'llmCostMarkupPercent' => $this->marginSettings->llmCostMarkupPercent(),
            'transactionFeePercent' => $this->marginSettings->transactionFeePercent(),
            'subscriptionMarkupPercent' => $this->marginSettings->subscriptionMarkupPercent(),
            'negotiationFeePercent' => $this->marginSettings->negotiationFeePercent(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'llm_cost_markup_percent' => ['required', 'numeric', 'min:0'],
            'transaction_fee_percent' => ['required', 'numeric', 'min:0'],
            'subscription_markup_percent' => ['required', 'numeric', 'min:0'],
            'negotiation_fee_percent' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data as $key => $value) {
            $this->marginSettings->set($key, (float) $value);
        }

        return redirect()->route('dashboard.nexus.margin-settings.index')->with('status', t('messages.nexus.admin.margin_settings.saved'));
    }
}
