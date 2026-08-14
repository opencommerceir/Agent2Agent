<?php

namespace Tests\Feature\Nexus\Automation;

use App\Domains\Nexus\Automation\Application\Actions\CreateAutoDiscoverRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreateInventoryAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreatePriceAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreateRecurringOrderRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\PauseAutomationRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\ProcessAutomationRulesAction;
use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRunLogRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\VerifyProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessAutomationRulesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_recurringOrder_neverTriggered_opensNegotiation(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $rule = app(CreateRecurringOrderRuleAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, 10_000, 'IRT', 2, 30);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(1, $result['triggered']);
        $this->assertSame(0, $result['failed']);

        $logs = app(AutomationRunLogRepositoryInterface::class)->findByRuleId($rule->id);
        $this->assertCount(1, $logs);
        $this->assertSame('triggered', $logs[0]->outcome()->value);

        $reloaded = app(AutomationRuleRepositoryInterface::class)->findById($rule->id);
        $this->assertNotNull($reloaded->lastTriggeredAt());
    }

    public function test_execute_recurringOrder_notYetDue_doesNotRetrigger(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        app(CreateRecurringOrderRuleAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, 10_000, 'IRT', 2, 30);

        app(ProcessAutomationRulesAction::class)->execute();
        $second = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(0, $second['triggered']);
    }

    public function test_execute_pausedRule_isNeverProcessed(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $rule = app(CreateRecurringOrderRuleAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, 10_000, 'IRT', 2, 30);
        app(PauseAutomationRuleAction::class)->execute($rule->id, $buyer->id);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(0, $result['triggered']);
    }

    public function test_execute_recurringOrder_insufficientCredit_logsFailedWithoutStoppingOtherRules(): void
    {
        $buyer = $this->verifiedBusinessWithZeroCredit('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $unrelatedBuyer = $this->verifiedBusiness('Unrelated Buyer Co');
        $unrelatedSeller = $this->verifiedBusiness('Unrelated Seller Co');

        app(CreateRecurringOrderRuleAction::class)->execute($unrelatedBuyer->id, $unrelatedSeller->id, CatalogItemType::Product, 1, 10_000, 'IRT', 1, 30);
        // Directly persist a broke buyer's rule (bypassing CreateRecurringOrderRuleAction's
        // own CostGate, which would itself throw for a zero-balance business).
        $rule = app(AutomationRuleRepositoryInterface::class)->save(
            AutomationRule::forRecurringOrder($buyer->id, $seller->id, CatalogItemType::Product, 1, 10_000, 'IRT', 1, 30)
        );

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(1, $result['triggered']); // the unrelated, funded rule
        $this->assertSame(1, $result['failed']);     // the broke rule

        $logs = app(AutomationRunLogRepositoryInterface::class)->findByRuleId($rule->id());
        $this->assertSame('failed', $logs[0]->outcome()->value);
    }

    public function test_execute_inventoryAlert_stockAboveThreshold_doesNotTrigger(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 50);
        app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 5);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(0, $result['triggered']);
    }

    public function test_execute_inventoryAlert_stockAtOrBelowThreshold_triggersAndLogsCandidateCount(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 2);
        $competitor = $this->verifiedBusiness('Competitor Co');
        app(AddProductAction::class)->execute($competitor->id, 'ویجت', 'Widget', 9_000, 'IRT', 20);
        $rule = app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 5);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(1, $result['triggered']);
        $logs = app(AutomationRunLogRepositoryInterface::class)->findByRuleId($rule->id);
        $this->assertStringContainsString('candidate supplier', $logs[0]->detail());
    }

    public function test_execute_inventoryAlert_respectsCooldown(): void
    {
        config(['nexus.platform.automation.alert_cooldown_hours' => 24]);
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 1);
        app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 5);

        app(ProcessAutomationRulesAction::class)->execute();
        $second = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(0, $second['triggered']);
    }

    public function test_execute_priceAlert_atOrBelow_triggersWhenConditionMet(): void
    {
        $watcher = $this->verifiedBusiness('Watcher Co');
        $watched = $this->verifiedBusiness('Watched Co');
        $product = app(AddProductAction::class)->execute($watched->id, 'محصول', 'Widget', 8_000, 'IRT', 10);
        app(CreatePriceAlertRuleAction::class)->execute($watcher->id, CatalogItemType::Product, $product->id, 10_000, PriceAlertDirection::AtOrBelow);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(1, $result['triggered']);
    }

    public function test_execute_priceAlert_atOrBelow_doesNotTriggerWhenPriceAboveTarget(): void
    {
        $watcher = $this->verifiedBusiness('Watcher Co');
        $watched = $this->verifiedBusiness('Watched Co');
        $product = app(AddProductAction::class)->execute($watched->id, 'محصول', 'Widget', 20_000, 'IRT', 10);
        app(CreatePriceAlertRuleAction::class)->execute($watcher->id, CatalogItemType::Product, $product->id, 10_000, PriceAlertDirection::AtOrBelow);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(0, $result['triggered']);
    }

    public function test_execute_autoDiscover_findsVerifiedCandidateAndOpensNegotiation(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $product = app(AddProductAction::class)->execute($seller->id, 'محصول', 'Widget', 10_000, 'IRT', 20);
        app(VerifyProductAction::class)->execute($product->id);
        $rule = app(CreateAutoDiscoverRuleAction::class)->execute($buyer->id, CatalogItemType::Product, 12_000, 'IRT', 2);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(1, $result['triggered']);
        $logs = app(AutomationRunLogRepositoryInterface::class)->findByRuleId($rule->id);
        $this->assertStringContainsString('opened with discovered Business', $logs[0]->detail());
    }

    public function test_execute_autoDiscover_skipsCandidateWithAlreadyOpenNegotiation(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $product = app(AddProductAction::class)->execute($seller->id, 'محصول', 'Widget', 10_000, 'IRT', 20);
        app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, $product->id,
            new NegotiationTerms(Money::fromAmount(9_000, 'IRT'), 1, null),
        );
        app(CreateAutoDiscoverRuleAction::class)->execute($buyer->id, CatalogItemType::Product, 12_000, 'IRT', 2);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        // The only same-industry candidate already has an open Negotiation
        // with the buyer — nothing new to discover this run.
        $this->assertSame(0, $result['triggered']);
    }

    public function test_execute_autoDiscover_skipsCandidateWithUnverifiedProduct(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        app(AddProductAction::class)->execute($seller->id, 'محصول', 'Widget', 10_000, 'IRT', 20); // starts Pending, never verified
        app(CreateAutoDiscoverRuleAction::class)->execute($buyer->id, CatalogItemType::Product, 12_000, 'IRT', 2);

        $result = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(0, $result['triggered']);
    }

    public function test_execute_autoDiscover_respectsCooldown(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        app(AddProductAction::class)->execute($seller->id, 'محصول', 'Widget', 10_000, 'IRT', 20);
        app(CreateAutoDiscoverRuleAction::class)->execute($buyer->id, CatalogItemType::Product, 12_000, 'IRT', 2);

        app(ProcessAutomationRulesAction::class)->execute();
        $second = app(ProcessAutomationRulesAction::class)->execute();

        $this->assertSame(0, $second['triggered']);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    private function verifiedBusinessWithZeroCredit(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        $balance = app(GetCreditBalanceAction::class)->execute($business->id)->balance;
        $this->assertSame(0, $balance);

        return $business;
    }
}
