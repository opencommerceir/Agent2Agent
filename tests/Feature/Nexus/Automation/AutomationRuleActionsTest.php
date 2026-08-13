<?php

namespace Tests\Feature\Nexus\Automation;

use App\Domains\Nexus\Automation\Application\Actions\CreateInventoryAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreatePriceAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreateRecurringOrderRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\DeleteAutomationRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\ListAutomationRulesAction;
use App\Domains\Nexus\Automation\Application\Actions\PauseAutomationRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\ResumeAutomationRuleAction;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AutomationRuleActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_createRecurringOrderRule_unknownCounterparty_throws(): void
    {
        $business = $this->verifiedBusiness('Caller Co');

        $this->expectException(InvalidArgumentException::class);

        app(CreateRecurringOrderRuleAction::class)->execute($business->id, 999999, CatalogItemType::Product, 1, 10_000, 'IRT', 1, 30);
    }

    public function test_createRecurringOrderRule_chargesCostGate(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $counterparty = $this->verifiedBusiness('Counterparty Co');
        $before = app(GetCreditBalanceAction::class)->execute($business->id)->balance;

        app(CreateRecurringOrderRuleAction::class)->execute($business->id, $counterparty->id, CatalogItemType::Product, 1, 10_000, 'IRT', 1, 30);

        $after = app(GetCreditBalanceAction::class)->execute($business->id)->balance;
        $this->assertSame(10, $before - $after);
    }

    public function test_createInventoryAlertRule_productNotOwnedByBusiness_throws(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $otherBusiness = $this->verifiedBusiness('Other Co');
        $product = app(AddProductAction::class)->execute($otherBusiness->id, 'محصول', 'Widget', 10_000, 'IRT', 5);

        $this->expectException(InvalidArgumentException::class);

        app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 3);
    }

    public function test_createInventoryAlertRule_succeedsForOwnProduct(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);

        $rule = app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 3);

        $this->assertSame('inventory_alert', $rule->type);
        $this->assertSame('active', $rule->status);
    }

    public function test_createPriceAlertRule_unknownCatalogItem_throws(): void
    {
        $business = $this->verifiedBusiness('Caller Co');

        $this->expectException(InvalidArgumentException::class);

        app(CreatePriceAlertRuleAction::class)->execute($business->id, CatalogItemType::Product, 999999, 5_000, PriceAlertDirection::AtOrBelow);
    }

    public function test_createPriceAlertRule_capturesCurrencyFromCatalogItem(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $watched = $this->verifiedBusiness('Watched Co');
        $product = app(AddProductAction::class)->execute($watched->id, 'محصول', 'Widget', 10_000, 'IRT', 5);

        $rule = app(CreatePriceAlertRuleAction::class)->execute($business->id, CatalogItemType::Product, $product->id, 5_000, PriceAlertDirection::AtOrBelow);

        $this->assertSame('IRT', $rule->config['currency']);
    }

    public function test_pauseThenResume_roundTrips(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $rule = app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 3);

        $paused = app(PauseAutomationRuleAction::class)->execute($rule->id, $business->id);
        $this->assertSame('paused', $paused->status);

        $resumed = app(ResumeAutomationRuleAction::class)->execute($rule->id, $business->id);
        $this->assertSame('active', $resumed->status);
    }

    public function test_pause_byNonOwner_throws(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $otherBusiness = $this->verifiedBusiness('Other Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $rule = app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 3);

        $this->expectException(InvalidArgumentException::class);

        app(PauseAutomationRuleAction::class)->execute($rule->id, $otherBusiness->id);
    }

    public function test_delete_removesRuleFromList(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $rule = app(CreateInventoryAlertRuleAction::class)->execute($business->id, $product->id, 3);

        app(DeleteAutomationRuleAction::class)->execute($rule->id, $business->id);

        $this->assertCount(0, app(ListAutomationRulesAction::class)->execute($business->id));
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
