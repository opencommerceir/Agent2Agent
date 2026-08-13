<?php

namespace App\Domains\Nexus\Automation\Application\Actions;

use App\Domains\Nexus\Automation\Application\DTOs\AutomationRuleData;
use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use InvalidArgumentException;

/**
 * "Inventory alert (auto-search)" (Phase 8/M4, roadmap: "هشدار موجودی
 * (auto-search)") — schedules ProcessAutomationRulesAction to, once a
 * Product's own stock drops to or below the threshold, automatically run
 * SearchMarketplaceAction (Extend, Don't Rebuild) for same-named listings
 * and notify the owner with the results — restocking candidates handed to
 * them, not just a bare "you're low" ping.
 */
final class CreateInventoryAlertRuleAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly AutomationRuleRepositoryInterface $rules,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(int $businessId, int $productId, int $thresholdQuantity): AutomationRuleData
    {
        $product = $this->products->findById($productId);

        if (! $product || $product->businessId() !== $businessId) {
            throw new InvalidArgumentException("Product [{$productId}] does not belong to Business [{$businessId}].");
        }

        $this->costGate->execute($businessId, 'nexus.automation.rule.create');

        $rule = $this->rules->save(AutomationRule::forInventoryAlert($businessId, $productId, $thresholdQuantity));

        return AutomationRuleData::fromEntity($rule);
    }
}
