<?php

namespace Tests\Feature\Finance;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Finance\Application\Actions\CreateInvoiceAction;
use App\Modules\Finance\Application\Actions\CreateTaxRateAction;
use App\Modules\Finance\Application\Actions\UpdateTaxRateAction;
use App\Modules\Finance\Domain\Exceptions\OrderNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CreateInvoiceAction's own fallback chain (region -> tenant DEFAULT ->
 * zero tax), exercised directly — distinct from
 * CommerceTaxIntegrationTest, which proves the *other* fallback chain
 * (Commerce's checkout pricing, which ends at a hardcoded 9%, not zero).
 */
class CreateInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withNoTaxRateConfiguredAtAll_chargesZeroTax(): void
    {
        [$tenantId, $agentId] = $this->setUpTenantAndAgent();
        $order = $this->placeOrderOf10000Cents($tenantId, $agentId);

        $invoice = app(CreateInvoiceAction::class)->execute($tenantId, $order->id);

        $this->assertSame(0, $invoice->taxAmount);
        $this->assertSame(10000, $invoice->totalAmount);
    }

    public function test_execute_withOnlyDefaultRegionConfigured_usesItWhenNoRegionGiven(): void
    {
        [$tenantId, $agentId] = $this->setUpTenantAndAgent();
        app(CreateTaxRateAction::class)->execute($tenantId, 'DEFAULT', 500);
        $order = $this->placeOrderOf10000Cents($tenantId, $agentId);

        $invoice = app(CreateInvoiceAction::class)->execute($tenantId, $order->id);

        $this->assertSame(500, $invoice->taxAmount);
        $this->assertSame(10500, $invoice->totalAmount);
    }

    public function test_execute_withInactiveSpecificRegion_fallsBackToDefault(): void
    {
        [$tenantId, $agentId] = $this->setUpTenantAndAgent();
        $rate = app(CreateTaxRateAction::class)->execute($tenantId, 'US-CA', 850);
        app(UpdateTaxRateAction::class)->execute($rate->id, $tenantId, 850, false);
        app(CreateTaxRateAction::class)->execute($tenantId, 'DEFAULT', 500);
        $order = $this->placeOrderOf10000Cents($tenantId, $agentId);

        $invoice = app(CreateInvoiceAction::class)->execute($tenantId, $order->id, 'US-CA');

        $this->assertSame(500, $invoice->taxAmount);
    }

    public function test_execute_forNonexistentOrder_throwsOrderNotFoundException(): void
    {
        [$tenantId] = $this->setUpTenantAndAgent();

        $this->expectException(OrderNotFoundException::class);

        app(CreateInvoiceAction::class)->execute($tenantId, 999999);
    }

    public function test_execute_forOrderInAnotherTenant_throwsOrderNotFoundException(): void
    {
        [$tenantA, $agentA] = $this->setUpTenantAndAgent();
        [$tenantB] = $this->setUpTenantAndAgent();
        $order = $this->placeOrderOf10000Cents($tenantA, $agentA);

        $this->expectException(OrderNotFoundException::class);

        app(CreateInvoiceAction::class)->execute($tenantB, $order->id);
    }

    public function test_execute_copiesOrderItemsWithProductNameAsDescription(): void
    {
        [$tenantId, $agentId] = $this->setUpTenantAndAgent();
        $order = $this->placeOrderOf10000Cents($tenantId, $agentId);

        $invoice = app(CreateInvoiceAction::class)->execute($tenantId, $order->id);

        $this->assertCount(1, $invoice->items);
        $this->assertSame('Widget', $invoice->items[0]['description']);
        $this->assertSame(1, $invoice->items[0]['quantity']);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function setUpTenantAndAgent(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Finance', 'acme-finance-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Finance Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return [$tenant->id, $agent->id];
    }

    private function placeOrderOf10000Cents(int $tenantId, int $agentId)
    {
        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 10000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, 10));

        $cart = app(AddToCartAction::class)->execute(
            tenantId: $tenantId,
            ownerType: MemberType::Agent,
            ownerId: $agentId,
            productId: $product->id,
            quantity: 1,
        );

        return app(PlaceOrderAction::class)->execute(tenantId: $tenantId, agentId: $agentId, cartId: $cart->id);
    }
}
