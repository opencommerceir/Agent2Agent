<?php

namespace Tests\Feature\Showcase;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCategoryAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The live side panel (Phase 2, §7.33) — each tab reuses the exact
 * Actions the Admin Dashboard's own read-only Controllers already call
 * (ListProductsAction/ListOrdersAction/GetDashboardStatsAction), so this
 * test builds only a minimal store fixture directly (not the full
 * 180-order DemoShowcaseSeeder — that's DemoShowcaseSeederTest's own,
 * slower job) tagged with the exact well-known Tenant slug
 * ShowcasePanelController looks up.
 */
class ShowcasePanelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_productsPanel_returnsRealSeededProducts(): void
    {
        $this->seedMinimalDemoStore();

        $response = $this->get('/showcase/panel/products');

        $response->assertStatus(200);
        $response->assertSee('Test Product One');
    }

    public function test_ordersPanel_returnsARealPlacedOrder(): void
    {
        $this->seedMinimalDemoStore();

        $response = $this->get('/showcase/panel/orders');

        $response->assertStatus(200);
        $response->assertSee('Confirmed');
    }

    public function test_kpisPanel_returnsRealComputedRevenue(): void
    {
        $this->seedMinimalDemoStore();

        $response = $this->get('/showcase/panel/kpis');

        // 2 units at $10.00 = $20.00 in real revenue, actually computed
        // via GetDashboardStatsAction -> CalculateKPIAction, not a stub.
        $response->assertStatus(200);
        $response->assertSee('20.00');
    }

    public function test_panels_withNoDemoTenantSeeded_renderAnEmptyStateNotAnError(): void
    {
        $response = $this->get('/showcase/panel/products');

        $response->assertStatus(200);
        $response->assertSee('Nothing to show yet.');
    }

    private function seedMinimalDemoStore(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Demo Showcase Store', DemoShowcaseSeeder::TENANT_SLUG);
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Demo Showcase HQ', DemoShowcaseSeeder::TENANT_SLUG.'-hq');
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, DemoShowcaseSeeder::DEMO_AGENT_NAME, 'custom');

        $category = app(CreateCategoryAction::class)->execute($tenant->id, 'Test Category');
        $product = app(CreateProductAction::class)->execute(
            tenantId: $tenant->id,
            name: 'Test Product One',
            sku: 'TEST-001',
            priceAmount: 1000,
            priceCurrency: 'USD',
            categoryId: $category->id,
            status: 'active',
        );
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 50));

        app(AddToCartAction::class)->execute(
            tenantId: $tenant->id,
            ownerType: MemberType::Agent,
            ownerId: $agent->id,
            productId: $product->id,
            quantity: 2,
        );

        $cart = app(CartRepositoryInterface::class)->findActiveByOwner($tenant->id, MemberType::Agent, $agent->id);

        app(ProcessPaymentAction::class)->execute(
            tenantId: $tenant->id,
            agentId: $agent->id,
            cartId: $cart->id(),
            paymentMethod: 'credit_card',
            paymentDetails: [],
        );
    }
}
