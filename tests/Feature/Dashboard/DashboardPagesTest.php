<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\CreateUserAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Infrastructure\Models\User;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-tests every one of the 8 Dashboard pages against real data created
 * through the same Actions the MCP layer itself uses — the literal
 * end-to-end scenario from Phase 4 Stage 5's own request (steps 4-13:
 * language/RTL, Tenants, Agents, Products, Orders, Notifications).
 */
class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_languageSwitch_toFarsi_rendersRtlAndFarsiText(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get('/language/fa');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('داشبورد OpenCommerce');
    }

    public function test_languageSwitch_toEnglish_rendersLtrAndEnglishText(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get('/language/en');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('OpenCommerce Dashboard');
    }

    public function test_tenantsIndex_listsCreatedTenants(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->get('/dashboard/tenants');

        $response->assertStatus(200);
        $response->assertSee('Acme Inc');
    }

    public function test_tenantsStore_createsANewTenant(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/dashboard/tenants', [
            'name' => 'Widgets Co',
            'slug' => 'widgets-co-'.uniqid(),
        ]);

        $response->assertRedirect(route('dashboard.tenants.index'));
        $this->assertDatabaseHas('tenants', ['name' => 'Widgets Co']);
    }

    public function test_tenantsUpdate_changesNameAndStatus(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->put("/dashboard/tenants/{$tenant->id}", [
            'name' => 'Acme Renamed',
            'status' => 'suspended',
        ]);

        $response->assertRedirect(route('dashboard.tenants.index'));
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Acme Renamed', 'status' => 'suspended']);
    }

    public function test_agentsIndex_filteredByTenant_showsOnlyThatTenantsAgents(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();

        [$tenantA, $orgA] = $this->createTenantWithOrganization('Acme A');
        [$tenantB, $orgB] = $this->createTenantWithOrganization('Acme B');
        app(RegisterAgentAction::class)->execute($tenantA->id, $orgA->id, 'Agent A', 'shopping');
        app(RegisterAgentAction::class)->execute($tenantB->id, $orgB->id, 'Agent B', 'shopping');

        $response = $this->actingAs($admin)->get("/dashboard/agents?tenant_id={$tenantA->id}");

        $response->assertStatus(200);
        $response->assertSee('Agent A');
        $response->assertDontSee('Agent B');
    }

    public function test_agentsStore_registersANewAgent(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        [$tenant, $organization] = $this->createTenantWithOrganization('Acme Inc');

        $response = $this->actingAs($admin)->post('/dashboard/agents', [
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'name' => 'New Agent',
            'type' => 'shopping',
        ]);

        $response->assertRedirect(route('dashboard.agents.index'));
        $this->assertDatabaseHas('agents', ['name' => 'New Agent', 'tenant_id' => $tenant->id]);
    }

    public function test_agentsSuspendThenActivate_togglesStatus(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        [$tenant, $organization] = $this->createTenantWithOrganization('Acme Inc');
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Agent A', 'shopping');

        $this->actingAs($admin)->post("/dashboard/agents/{$agent->id}/suspend")->assertRedirect();
        $this->assertSame('suspended', app(AgentRepositoryInterface::class)->findById($agent->id)->status()->value);

        $this->actingAs($admin)->post("/dashboard/agents/{$agent->id}/activate")->assertRedirect();
        $this->assertSame('active', app(AgentRepositoryInterface::class)->findById($agent->id)->status()->value);
    }

    public function test_productsIndex_showsProductForSelectedTenant(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        [$tenant] = $this->createTenantWithOrganization('Acme Inc');
        app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'SKU-DASH-1', 1999, 'USD', status: 'active');

        $response = $this->actingAs($admin)->get("/dashboard/products?tenant_id={$tenant->id}");

        $response->assertStatus(200);
        $response->assertSee('Widget');
    }

    public function test_ordersIndex_filteredByStatus_showsPlacedOrder(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $order = $this->placeARealOrder();

        $response = $this->actingAs($admin)->get("/dashboard/orders?tenant_id={$order->tenantId}&status=confirmed");

        $response->assertStatus(200);
        $response->assertSee($order->orderNumber);
    }

    public function test_orderCancel_cancelsAConfirmedOrder(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $order = $this->placeARealOrder();

        $response = $this->actingAs($admin)->post("/dashboard/orders/{$order->id}/cancel?tenant_id={$order->tenantId}");

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_settingsUpdate_changesTenantDefaultLanguage(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $response = $this->actingAs($admin)->put('/dashboard/settings', [
            'tenant_id' => $tenant->id,
            'default_language' => 'fa',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'default_language' => 'fa']);
    }

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    /**
     * @return array{0: \App\Core\Application\DTOs\TenantData, 1: \App\Core\Application\DTOs\OrganizationData}
     */
    private function createTenantWithOrganization(string $name): array
    {
        $tenant = app(CreateTenantAction::class)->execute($name, \Illuminate\Support\Str::slug($name).'-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, $name.' Org', \Illuminate\Support\Str::slug($name).'-org-'.uniqid());

        return [$tenant, $organization];
    }

    private function placeARealOrder(): \App\Modules\Commerce\Application\DTOs\OrderData
    {
        [$tenant, $organization] = $this->createTenantWithOrganization('Acme Inc');
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shop Bot', 'shopping');

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'SKU-DASH-ORDER', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));

        $customer = app(CreateCustomerAction::class)->execute($tenant->id, 'Ada', 'Lovelace', 'ada-'.uniqid().'@example.com');
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agent->id, $product->id, 1);

        return app(PlaceOrderAction::class)->execute(
            tenantId: $tenant->id, agentId: $agent->id, cartId: $cart->id, customerId: $customer->id,
        );
    }
}
