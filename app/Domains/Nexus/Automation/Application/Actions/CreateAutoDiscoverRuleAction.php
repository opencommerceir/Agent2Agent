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
 * The proactive half of the Autonomous Agent Runtime — unlike
 * CreateRecurringOrderRuleAction, the Business does NOT name a
 * counterparty here; ProcessAutomationRulesAction::processAutoDiscover()
 * finds one itself each run via the existing GetRecommendationsAction.
 * Same anti-spam flat-fee CostGate convention every other rule-creation
 * Action in this domain already uses.
 */
final class CreateAutoDiscoverRuleAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly AutomationRuleRepositoryInterface $rules,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(
        int $businessId,
        CatalogItemType $catalogItemType,
        int $maxPriceAmount,
        string $priceCurrency,
        int $quantity,
    ): AutomationRuleData {
        if (! $this->businesses->findById($businessId)) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $this->costGate->execute($businessId, 'nexus.automation.rule.create');

        $rule = $this->rules->save(AutomationRule::forAutoDiscover(
            businessId: $businessId,
            catalogItemType: $catalogItemType,
            maxPriceAmount: $maxPriceAmount,
            priceCurrency: $priceCurrency,
            quantity: $quantity,
        ));

        return AutomationRuleData::fromEntity($rule);
    }
}
