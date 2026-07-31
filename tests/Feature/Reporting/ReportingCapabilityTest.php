<?php

namespace Tests\Feature\Reporting;

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
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Reporting\Application\Actions\GetReportAction;
use App\Modules\Reporting\Domain\Exceptions\ReportNotFoundException;
use Database\Seeders\LoyaltyCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full Phase 3.5 (Reporting) scenario over real MCP HTTP requests
 * plus real Commerce Action calls: 5 Customers, 10 Products, 20 real
 * paid Orders (real Payments, real tax, real Loyalty points earned via
 * OrderPlacedListener — no faking), then every one of the 5 Generate*
 * capabilities is exercised and checked against independently
 * accumulated expected totals (not re-derived report-side math), plus
 * tenant isolation and an invalid date range.
 */
class ReportingCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullReportingScenario(): void
    {
        $this->seed(ReportingCapabilitiesSeeder::class);
        $this->seed(LoyaltyCapabilitiesSeeder::class);

        [$tenantA, $agentA, $tokenA] = $this->registerAgentWithPermissions([
            'reporting.sales.read', 'reporting.products.read',
            'reporting.customers.read', 'reporting.revenue.read', 'reporting.loyalty.read',
        ]);

        // 5 Customers.
        $customers = [];
        for ($c = 0; $c < 5; $c++) {
            $customers[] = app(CreateCustomerAction::class)->execute($tenantA, "Customer{$c}", 'Test', "customer{$c}-".uniqid().'@example.com');
        }

        // 10 Products, each with ample stock (well above any order
        // quantity, staying clear of CheckInventoryAction's re-check
        // quirk for orders over half of on-hand stock — HANDOFF §8.22).
        $products = [];
        for ($p = 0; $p < 10; $p++) {
            $product = app(CreateProductAction::class)->execute($tenantA, "Product{$p}", "SKU-{$p}", 1000 + ($p * 100), 'USD', status: 'active');
            app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 1000));
            $products[] = $product;
        }

        // 20 real, paid Orders. Product `i % 10`'s quantity is
        // `10 - (i % 10)`, deterministically decreasing — product 0
        // always ends up with the single highest quantity_sold (2
        // orders × 10 units = 20), giving the Top Products Report a
        // guaranteed, unambiguous #1 to assert against without having to
        // hand-derive Eloquent's own aggregation.
        $expectedTotalSales = 0;
        $expectedGrossRevenue = 0;
        $expectedTaxCollected = 0;
        $expectedPointsEarned = 0;

        for ($i = 0; $i < 20; $i++) {
            $customer = $customers[$i % 5];
            $product = $products[$i % 10];
            $quantity = 10 - ($i % 10);

            $cart = app(AddToCartAction::class)->execute($tenantA, MemberType::Agent, $agentA, $product->id, $quantity);

            $result = app(ProcessPaymentAction::class)->execute(
                tenantId: $tenantA,
                agentId: $agentA,
                cartId: $cart->id,
                paymentMethod: 'credit_card',
                paymentDetails: ['card_number' => '4242424242424242'],
                customerId: $customer->id,
            );

            $order = $result['order'];
            $expectedTotalSales += $order->totalAmount;
            $expectedGrossRevenue += $order->subtotalAmount;
            $expectedTaxCollected += $order->taxAmount;
            $expectedPointsEarned += intdiv($order->totalAmount, 100);
        }

        $startDate = now()->subDay()->format('Y-m-d');
        $endDate = now()->addDay()->format('Y-m-d');

        // Sales Report.
        $sales = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.sales.generate',
            'input' => ['start_date' => $startDate, 'end_date' => $endDate],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $sales->assertStatus(200);
        $this->assertSame(20, $sales->json('data.report.totalOrders'));
        $this->assertSame($expectedTotalSales, $sales->json('data.report.totalSales'));
        $this->assertSame(intdiv($expectedTotalSales, 20), $sales->json('data.report.averageOrderValue'));

        // Top Products Report — top 3, product 0 guaranteed #1.
        $topProducts = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.products.top',
            'input' => ['start_date' => $startDate, 'end_date' => $endDate, 'limit' => 3],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $topProducts->assertStatus(200);
        $topList = $topProducts->json('data.report.products');
        $this->assertCount(3, $topList);
        $this->assertSame($products[0]->id, $topList[0]['productId']);
        $this->assertSame('Product0', $topList[0]['name']);
        $this->assertSame(20, $topList[0]['quantitySold']);

        // Top Customers Report — only 5 customers exist, limit 3.
        $topCustomers = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.customers.top',
            'input' => ['start_date' => $startDate, 'end_date' => $endDate, 'limit' => 3],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $topCustomers->assertStatus(200);
        $customerList = $topCustomers->json('data.report.customers');
        $this->assertCount(3, $customerList);
        foreach ($customerList as $row) {
            $this->assertGreaterThan(0, $row['totalSpent']);
            $this->assertGreaterThan(0, $row['totalOrders']);
        }

        // Revenue Report.
        $revenue = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.revenue.generate',
            'input' => ['start_date' => $startDate, 'end_date' => $endDate],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $revenue->assertStatus(200);
        $this->assertSame($expectedGrossRevenue, $revenue->json('data.report.grossRevenue'));
        $this->assertSame($expectedTaxCollected, $revenue->json('data.report.taxCollected'));
        $this->assertSame(0, $revenue->json('data.report.discountsApplied'));
        $this->assertSame($expectedGrossRevenue, $revenue->json('data.report.netRevenue'));

        // Loyalty Report — every Order carried a customer_id, so
        // OrderPlacedListener earned points for every single one.
        $loyalty = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.loyalty.generate',
            'input' => ['start_date' => $startDate, 'end_date' => $endDate],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $loyalty->assertStatus(200);
        $this->assertSame($expectedPointsEarned, $loyalty->json('data.report.totalPointsEarned'));
        $this->assertSame(0, $loyalty->json('data.report.totalPointsRedeemed'));
        $this->assertSame(5, $loyalty->json('data.report.activeAccounts'));

        // Tenant B's Agent cannot see Tenant A's saved Report
        // (GetReportAction is not wired to MCP this stage — exercised
        // directly, same shape ExpirePointsActionTest exercises Loyalty's
        // own un-wired Action).
        $reportId = $this->latestSalesReportId($tenantA);
        [$tenantB] = $this->registerAgentWithPermissions([]);

        $this->expectException(ReportNotFoundException::class);
        app(GetReportAction::class)->execute($reportId, $tenantB);
    }

    public function test_generateSalesReport_withEndDateBeforeStartDate_returnsValidationError(): void
    {
        $this->seed(ReportingCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions(['reporting.sales.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.sales.generate',
            'input' => ['start_date' => '2026-07-31', 'end_date' => '2026-07-01'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_generateSalesReport_withNoOrders_returnsZeroedReport(): void
    {
        $this->seed(ReportingCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions(['reporting.sales.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.sales.generate',
            'input' => ['start_date' => '2020-01-01', 'end_date' => '2020-01-31'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.report.totalSales'));
        $this->assertSame(0, $response->json('data.report.totalOrders'));
        $this->assertSame(0, $response->json('data.report.averageOrderValue'));
        $this->assertSame([], $response->json('data.report.salesByDay'));
    }

    public function test_generateSalesReport_withoutPermission_returnsForbidden(): void
    {
        $this->seed(ReportingCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'report.sales.generate',
            'input' => ['start_date' => '2026-01-01', 'end_date' => '2026-01-31'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    private function latestSalesReportId(int $tenantId): int
    {
        /** @var \App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface $reports */
        $reports = app(\App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface::class);
        $list = $reports->list($tenantId, \App\Modules\Reporting\Domain\ValueObjects\ReportType::Sales, 1);

        return $list[0]->id();
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

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
