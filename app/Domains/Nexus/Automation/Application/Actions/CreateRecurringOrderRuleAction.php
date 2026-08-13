<?php

namespace App\Domains\Nexus\Automation\Application\Actions;

use App\Domains\Nexus\Automation\Application\DTOs\AutomationRuleData;
use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use InvalidArgumentException;

/**
 * "Recurring orders" (Phase 8/M4, roadmap: "سفارشات تکرارشونده") —
 * schedules ProcessAutomationRulesAction to auto-open a fresh Negotiation
 * with the same counterparty/item/price/quantity every N days, reusing
 * InitiateNegotiationAction (Extend, Don't Rebuild) rather than inventing a
 * second "place an order" mechanism. Priced the same small flat fee as
 * every other rule-creation Action in this domain, anti-spam only — the
 * real, recurring cost is each triggered Negotiation's own existing
 * nexus.negotiation.propose CostGate charge.
 */
final class CreateRecurringOrderRuleAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly AutomationRuleRepositoryInterface $rules,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(
        int $businessId,
        int $counterpartyBusinessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        int $priceAmount,
        string $priceCurrency,
        int $quantity,
        int $intervalDays,
    ): AutomationRuleData {
        if (! $this->businesses->findById($businessId)) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        if (! $this->businesses->findById($counterpartyBusinessId)) {
            throw new InvalidArgumentException("Business [{$counterpartyBusinessId}] does not exist.");
        }

        $this->costGate->execute($businessId, 'nexus.automation.rule.create');

        $rule = $this->rules->save(AutomationRule::forRecurringOrder(
            businessId: $businessId,
            counterpartyBusinessId: $counterpartyBusinessId,
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            priceAmount: $priceAmount,
            priceCurrency: $priceCurrency,
            quantity: $quantity,
            intervalDays: $intervalDays,
        ));

        return AutomationRuleData::fromEntity($rule);
    }
}
