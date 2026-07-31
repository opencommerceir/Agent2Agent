<?php

namespace Tests\Feature\Finance;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\FinanceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full Phase 3.2 (Finance) scenario over real MCP HTTP requests: a
 * tax rate for US-CA -> a $100 Order -> an Invoice created from that
 * Order in the US-CA region (tax = 8.50, total = 108.50) -> issuing it
 * -> tenant isolation on `finance.invoice.get` -> listing Invoices
 * filtered by status.
 */
class FinanceCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullInvoiceLifecycleScenario(): void
    {
        $this->seed(FinanceCapabilitiesSeeder::class);

        [$tenantA, $agentA, $tokenA] = $this->registerAgentWithPermissions([
            'finance.tax.manage', 'finance.tax.read', 'finance.invoices.create', 'finance.invoices.manage', 'finance.invoices.read',
        ]);

        // Step 1: a tax rate for US-CA at 8.5%.
        $taxCreate = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.tax.create',
            'input' => ['region' => 'US-CA', 'rate_percentage' => 850],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $taxCreate->assertStatus(200);
        $taxCreate->assertJsonPath('data.tax_rate.ratePercentage', 850);

        // Step 2: a $100 Order (plain order.place — no pricing, subtotal = total = 10000).
        $order = $this->placeOrderOf10000Cents($tenantA, $agentA);

        // Step 3: create an Invoice from that Order in the US-CA region.
        $invoiceCreate = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.invoice.create',
            'input' => ['order_id' => $order->id, 'region' => 'US-CA'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $invoiceCreate->assertStatus(200);
        $invoiceCreate->assertJsonPath('data.invoice.status', 'draft');
        $invoiceCreate->assertJsonPath('data.invoice.subtotalAmount', 10000);
        $invoiceCreate->assertJsonPath('data.invoice.taxAmount', 850);
        $invoiceCreate->assertJsonPath('data.invoice.totalAmount', 10850);
        $invoiceId = $invoiceCreate->json('data.invoice.id');

        // Step 4: issue it.
        $issue = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.invoice.issue',
            'input' => ['invoice_id' => $invoiceId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $issue->assertStatus(200);
        $issue->assertJsonPath('data.invoice.status', 'issued');
        $this->assertNotNull($issue->json('data.invoice.issuedAt'));

        // Step 5: Tenant B's Agent cannot see Tenant A's Invoice.
        [, , $tokenB] = $this->registerAgentWithPermissions(['finance.invoices.read']);

        $crossTenant = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.invoice.get',
            'input' => ['invoice_id' => $invoiceId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenant->assertStatus(404);
        $crossTenant->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 6: list Invoices filtered by status.
        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.invoice.list',
            'input' => ['status' => 'issued'],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $list->assertStatus(200);
        $numbers = collect($list->json('data.invoices'))->pluck('invoiceNumber');
        $this->assertTrue($numbers->contains($invoiceCreate->json('data.invoice.invoiceNumber')));
    }

    public function test_calculateTax_withKnownRegion_returnsTaxAndTotal(): void
    {
        $this->seed(FinanceCapabilitiesSeeder::class);
        [$tenantId, , $token] = $this->registerAgentWithPermissions(['finance.tax.manage', 'finance.tax.read']);

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.tax.create',
            'input' => ['region' => 'US-CA', 'rate_percentage' => 850],
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.tax.calculate',
            'input' => ['amount' => 10000, 'currency' => 'USD', 'region' => 'US-CA'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.tax_amount', 850);
        $response->assertJsonPath('data.total_amount', 10850);
    }

    public function test_calculateTax_withUnknownRegion_returnsNotFound(): void
    {
        $this->seed(FinanceCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions(['finance.tax.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.tax.calculate',
            'input' => ['amount' => 10000, 'currency' => 'USD', 'region' => 'US-NY'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_createInvoice_forNonexistentOrder_returnsNotFound(): void
    {
        $this->seed(FinanceCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions(['finance.invoices.create']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.invoice.create',
            'input' => ['order_id' => 999999],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_createTaxRate_withDuplicateRegion_returnsConflict(): void
    {
        $this->seed(FinanceCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions(['finance.tax.manage']);

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.tax.create',
            'input' => ['region' => 'US-CA', 'rate_percentage' => 850],
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.tax.create',
            'input' => ['region' => 'US-CA', 'rate_percentage' => 900],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'CONFLICT');
    }

    public function test_createTaxRate_withoutPermission_returnsForbidden(): void
    {
        $this->seed(FinanceCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'finance.tax.create',
            'input' => ['region' => 'US-CA', 'rate_percentage' => 850],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @return \App\Modules\Commerce\Application\DTOs\OrderData
     */
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

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Finance', 'acme-finance-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Finance Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Finance Operator', 'finance-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
