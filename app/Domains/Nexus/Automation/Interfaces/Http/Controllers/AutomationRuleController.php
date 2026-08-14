<?php

namespace App\Domains\Nexus\Automation\Interfaces\Http\Controllers;

use App\Domains\Nexus\Automation\Application\Actions\CreateAutoDiscoverRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreateInventoryAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreatePriceAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreateRecurringOrderRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\DeleteAutomationRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\ListAutomationRulesAction;
use App\Domains\Nexus\Automation\Application\Actions\PauseAutomationRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\ResumeAutomationRuleAction;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-facing Automation Workflows UI (Phase 8/M4) — the human-operated
 * counterpart to the nexus.automation.rule.* MCP capabilities an Agent can
 * also call directly, same shape CoalitionController already established.
 * A single POST endpoint branches on the submitted `type` field to one of
 * the three Create*RuleAction classes rather than three separate routes —
 * the roadmap's "Visual workflow builder" is, honestly, a guided
 * type-specific form here (see AutomationRule's own docblock for why: no
 * drag-and-drop diagramming dependency exists in this codebase).
 */
class AutomationRuleController extends Controller
{
    public function __construct(
        private readonly ListAutomationRulesAction $listAutomationRules,
        private readonly CreateRecurringOrderRuleAction $createRecurringOrderRule,
        private readonly CreateInventoryAlertRuleAction $createInventoryAlertRule,
        private readonly CreatePriceAlertRuleAction $createPriceAlertRule,
        private readonly CreateAutoDiscoverRuleAction $createAutoDiscoverRule,
        private readonly PauseAutomationRuleAction $pauseAutomationRule,
        private readonly ResumeAutomationRuleAction $resumeAutomationRule,
        private readonly DeleteAutomationRuleAction $deleteAutomationRule,
    ) {
    }

    public function index(): View
    {
        return view('nexus::automation.index', [
            'rules' => $this->listAutomationRules->execute($this->actingBusinessId()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('nexus::automation.create', [
            'type' => $request->string('type')->toString() ?: 'recurring_order',
            'catalogItemTypes' => CatalogItemType::cases(),
            'directions' => PriceAlertDirection::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = $this->actingBusinessId();
        $type = $request->string('type')->toString();

        match ($type) {
            'recurring_order' => $this->storeRecurringOrder($request, $businessId),
            'inventory_alert' => $this->storeInventoryAlert($request, $businessId),
            'price_alert' => $this->storePriceAlert($request, $businessId),
            'auto_discover' => $this->storeAutoDiscover($request, $businessId),
            default => abort(422, 'Unknown automation rule type.'),
        };

        return redirect()->route('nexus.automation.index');
    }

    public function pause(int $rule): RedirectResponse
    {
        $this->pauseAutomationRule->execute($rule, $this->actingBusinessId());

        return redirect()->route('nexus.automation.index');
    }

    public function resume(int $rule): RedirectResponse
    {
        $this->resumeAutomationRule->execute($rule, $this->actingBusinessId());

        return redirect()->route('nexus.automation.index');
    }

    public function destroy(int $rule): RedirectResponse
    {
        $this->deleteAutomationRule->execute($rule, $this->actingBusinessId());

        return redirect()->route('nexus.automation.index');
    }

    private function storeRecurringOrder(Request $request, int $businessId): void
    {
        $validated = $request->validate([
            'counterparty_business_id' => ['required', 'integer'],
            'catalog_item_type' => ['required', 'string'],
            'catalog_item_id' => ['required', 'integer'],
            'price_amount' => ['required', 'integer', 'min:1'],
            'price_currency' => ['required', 'string', 'size:3'],
            'quantity' => ['required', 'integer', 'min:1'],
            'interval_days' => ['required', 'integer', 'min:1'],
        ]);

        $this->createRecurringOrderRule->execute(
            businessId: $businessId,
            counterpartyBusinessId: (int) $validated['counterparty_business_id'],
            catalogItemType: CatalogItemType::from($validated['catalog_item_type']),
            catalogItemId: (int) $validated['catalog_item_id'],
            priceAmount: (int) $validated['price_amount'],
            priceCurrency: $validated['price_currency'],
            quantity: (int) $validated['quantity'],
            intervalDays: (int) $validated['interval_days'],
        );
    }

    private function storeInventoryAlert(Request $request, int $businessId): void
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'threshold_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $this->createInventoryAlertRule->execute($businessId, (int) $validated['product_id'], (int) $validated['threshold_quantity']);
    }

    private function storePriceAlert(Request $request, int $businessId): void
    {
        $validated = $request->validate([
            'catalog_item_type' => ['required', 'string'],
            'catalog_item_id' => ['required', 'integer'],
            'target_price_amount' => ['required', 'integer', 'min:0'],
            'direction' => ['required', 'string'],
        ]);

        $this->createPriceAlertRule->execute(
            businessId: $businessId,
            catalogItemType: CatalogItemType::from($validated['catalog_item_type']),
            catalogItemId: (int) $validated['catalog_item_id'],
            targetPriceAmount: (int) $validated['target_price_amount'],
            direction: PriceAlertDirection::from($validated['direction']),
        );
    }

    private function storeAutoDiscover(Request $request, int $businessId): void
    {
        $validated = $request->validate([
            'catalog_item_type' => ['required', 'string'],
            'max_price_amount' => ['required', 'integer', 'min:1'],
            'price_currency' => ['required', 'string', 'size:3'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->createAutoDiscoverRule->execute(
            businessId: $businessId,
            catalogItemType: CatalogItemType::from($validated['catalog_item_type']),
            maxPriceAmount: (int) $validated['max_price_amount'],
            priceCurrency: $validated['price_currency'],
            quantity: (int) $validated['quantity'],
        );
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}
