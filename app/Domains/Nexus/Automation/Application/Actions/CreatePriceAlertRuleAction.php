<?php

namespace App\Domains\Nexus\Automation\Application\Actions;

use App\Domains\Nexus\Automation\Application\DTOs\AutomationRuleData;
use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use InvalidArgumentException;

/**
 * "Price alert" (Phase 8/M4, roadmap: "هشدار قیمت") — notifies the watching
 * Business once a catalog item's live listed price crosses a target
 * threshold. The watched item can belong to ANY Business, including a
 * competitor's — this is public catalog data (same visibility
 * SearchMarketplaceAction already exposes), not a private relationship the
 * way nexus.marketplace.network is.
 */
final class CreatePriceAlertRuleAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
        private readonly AutomationRuleRepositoryInterface $rules,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(
        int $businessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        int $targetPriceAmount,
        PriceAlertDirection $direction,
    ): AutomationRuleData {
        $currency = $catalogItemType === CatalogItemType::Product
            ? $this->products->findById($catalogItemId)?->price()->currency()
            : $this->services->findById($catalogItemId)?->hourlyPrice()->currency();

        if ($currency === null) {
            throw new InvalidArgumentException("Catalog item [{$catalogItemType->value}#{$catalogItemId}] does not exist.");
        }

        $this->costGate->execute($businessId, 'nexus.automation.rule.create');

        $rule = $this->rules->save(AutomationRule::forPriceAlert(
            businessId: $businessId,
            catalogItemType: $catalogItemType,
            catalogItemId: $catalogItemId,
            targetPriceAmount: $targetPriceAmount,
            currency: $currency,
            direction: $direction,
        ));

        return AutomationRuleData::fromEntity($rule);
    }
}
