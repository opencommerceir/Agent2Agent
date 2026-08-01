<?php

namespace Tests\Feature\Analytics;

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
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from Phase 4 Stage 6's own request:
 * 10 real paid Orders with different amounts -> Revenue KPI matches the
 * real sum -> Average Order Value matches the real average -> a Snapshot
 * is generated and persisted -> Dashboard stats reflect the same numbers
 * -> tenant isolation -> CSV/PDF export.
 */
class AnalyticsCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullAnalyticsScenario(): void
    {
        Storage::fake('public');

        [$tenantId, $agentId, $token] = $this->registerAgentWithPermissions([
            'analytics.kpis.read', 'analytics.dashboard.read', 'analytics.snapshots.create', 'analytics.reports.export',
        ]);

        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'SKU-ANALYTICS-1', 1000, 'USD', status: 'active');

        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, 1000));

        $expectedGrossRevenue = 0;
        $expectedTotalAmountSum = 0;

        for ($quantity = 1; $quantity <= 10; $quantity++) {
            $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $product->id, $quantity);

            $result = app(ProcessPaymentAction::class)->execute(
                tenantId: $tenantId,
                agentId: $agentId,
                cartId: $cart->id,
                paymentMethod: 'credit_card',
                paymentDetails: [],
            );

            $expectedGrossRevenue += 1000 * $quantity;
            $expectedTotalAmountSum += $result['order']->totalAmount;
        }

        $today = now();
        $startDate = $today->copy()->startOfMonth()->format('Y-m-d');
        $endDate = $today->format('Y-m-d');

        // Step 2: Revenue KPI matches the real sum.
        $revenue = $this->calculateKpi($token, 'revenue', $startDate, $endDate);
        $revenue->assertStatus(200);
        $this->assertSame($expectedGrossRevenue, $revenue->json('data.kpi.amount'));
        $this->assertSame('USD', $revenue->json('data.kpi.unit'));

        // Step 3: Average Order Value matches the real average.
        $aov = $this->calculateKpi($token, 'average_order_value', $startDate, $endDate);
        $aov->assertStatus(200);
        $this->assertSame(intdiv($expectedTotalAmountSum, 10), $aov->json('data.kpi.amount'));

        // Step 4: Generate Snapshot -> persisted.
        $snapshot = $this->postJson('/mcp/v1/execute', [
            'capability' => 'analytics.snapshot.generate',
        ], ['Authorization' => "Bearer {$token}"]);
        $snapshot->assertStatus(200);
        $this->assertSame(10, $snapshot->json('data.snapshot.totalOrders'));
        $this->assertSame($expectedGrossRevenue, $snapshot->json('data.snapshot.totalRevenueCents'));
        $this->assertDatabaseHas('analytics_snapshots', ['tenant_id' => $tenantId, 'total_orders' => 10]);

        // Step 5: Dashboard stats reflect the same numbers (same calendar month).
        $stats = $this->postJson('/mcp/v1/execute', [
            'capability' => 'analytics.dashboard.stats',
        ], ['Authorization' => "Bearer {$token}"]);
        $stats->assertStatus(200);
        $this->assertSame(10, $stats->json('data.stats.totalOrders'));
        $this->assertSame($expectedGrossRevenue, $stats->json('data.stats.totalRevenueCents'));
        $this->assertCount(1, $stats->json('data.stats.topProducts'));

        // Step 6 (tenant isolation): a different Tenant's Agent sees zero.
        [, , $tokenB] = $this->registerAgentWithPermissions(['analytics.kpis.read']);
        $revenueB = $this->calculateKpi($tokenB, 'revenue', $startDate, $endDate);
        $revenueB->assertStatus(200);
        $this->assertSame(0, $revenueB->json('data.kpi.amount'));

        // Step 7: Export to CSV.
        $csv = $this->postJson('/mcp/v1/execute', [
            'capability' => 'analytics.report.export',
            'input' => ['report_type' => 'kpi_summary', 'format' => 'csv', 'start_date' => $startDate, 'end_date' => $endDate],
        ], ['Authorization' => "Bearer {$token}"]);
        $csv->assertStatus(200);
        $csvUrl = $csv->json('data.file_url');
        $this->assertNotEmpty($csvUrl);
        $csvPath = ltrim(parse_url($csvUrl, PHP_URL_PATH), '/');
        $csvPath = preg_replace('#^storage/#', '', $csvPath);
        Storage::disk('public')->assertExists($csvPath);

        // Step 8: Export to PDF.
        $pdf = $this->postJson('/mcp/v1/execute', [
            'capability' => 'analytics.report.export',
            'input' => ['report_type' => 'kpi_summary', 'format' => 'pdf', 'start_date' => $startDate, 'end_date' => $endDate],
        ], ['Authorization' => "Bearer {$token}"]);
        $pdf->assertStatus(200);
        $pdfUrl = $pdf->json('data.file_url');
        $this->assertNotEmpty($pdfUrl);
        $pdfPath = preg_replace('#^storage/#', '', ltrim(parse_url($pdfUrl, PHP_URL_PATH), '/'));
        Storage::disk('public')->assertExists($pdfPath);
    }

    public function test_kpiList_returnsDefinitionsCreatedByPriorCalculations(): void
    {
        [$tenantId, $agentId, $token] = $this->registerAgentWithPermissions(['analytics.kpis.read']);

        $this->calculateKpi($token, 'total_orders', now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d'))->assertStatus(200);

        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'analytics.kpi.list',
        ], ['Authorization' => "Bearer {$token}"]);

        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data.kpis'));
        $this->assertSame('total_orders', $list->json('data.kpis.0.type'));
    }

    private function calculateKpi(string $token, string $kpiType, string $startDate, string $endDate)
    {
        return $this->postJson('/mcp/v1/execute', [
            'capability' => 'analytics.kpi.calculate',
            'input' => [
                'kpi_type' => $kpiType,
                'time_period' => 'monthly',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ], ['Authorization' => "Bearer {$token}"]);
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(AnalyticsCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
