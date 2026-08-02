<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\ExportOrdersAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Action+Job-level tests for the Orders CSV export (Phase 5, Stage 3,
 * §7.23). `ProcessBulkExportJob` runs synchronously under this suite's
 * `sync` queue driver, so by the time `ExportOrdersAction::execute()`
 * returns below, the export file already exists.
 *
 * Order::place() always stamps createdAt as "now" (it's a readonly field
 * set only at construction) — there is no domain-level way to place an
 * Order dated in the past. Date-range filtering is proven here the same
 * pragmatic way any Feature test with real DB access can: place real
 * Orders, then directly rewrite their `created_at` column, and assert the
 * export honors whatever `OrderRepositoryInterface::listByTenant()`'s own
 * `created_at` column comparison actually sees.
 */
class ExportOrdersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_filtersToDateRangeAndResolvesCustomerEmail(): void
    {
        Storage::fake('public');

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $productId = $this->createStockedProduct($tenant->id, 100);

        $customer = app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Doe', 'jane@example.com');

        $inRangeWithCustomer = $this->placeOrder($tenant->id, $agentId, $productId, 1, $customer->id);
        $this->setOrderCreatedAt($inRangeWithCustomer->id, '2026-01-15 10:00:00');

        $inRangeNoCustomer = $this->placeOrder($tenant->id, $agentId, $productId, 2);
        $this->setOrderCreatedAt($inRangeNoCustomer->id, '2026-01-05 08:00:00');

        $outOfRange = $this->placeOrder($tenant->id, $agentId, $productId, 3);
        $this->setOrderCreatedAt($outOfRange->id, '2026-02-01 08:00:00');

        $result = app(ExportOrdersAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            startDate: '2026-01-01',
            endDate: '2026-01-31',
        );

        $this->assertSame('completed', $result['operation']->status);
        $this->assertSame(2, $result['operation']->totalRows);
        $this->assertSame(2, $result['operation']->successRows);
        $this->assertNotNull($result['operation']->filePath);
        $this->assertNotNull($result['downloadUrl']);

        Storage::disk('public')->assertExists($result['operation']->filePath);
        $csv = Storage::disk('public')->get($result['operation']->filePath);

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertSame(['order_number', 'customer_email', 'total_amount', 'status', 'created_at'], str_getcsv($lines[0]));
        $this->assertCount(3, $lines); // header + 2 in-range orders

        $this->assertStringContainsString('jane@example.com', $csv);
        $this->assertStringNotContainsString($outOfRange->orderNumber, $csv);
        $this->assertStringContainsString($inRangeWithCustomer->orderNumber, $csv);
        $this->assertStringContainsString($inRangeNoCustomer->orderNumber, $csv);

        $rows = array_map('str_getcsv', array_slice($lines, 1));
        $totals = array_column($rows, 2);
        sort($totals);
        $this->assertSame(['10.00', '20.00'], $totals);
    }

    public function test_export_withoutAnyDateRange_includesEveryOrder(): void
    {
        Storage::fake('public');

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $productId = $this->createStockedProduct($tenant->id, 100);

        $this->placeOrder($tenant->id, $agentId, $productId, 1);
        $this->placeOrder($tenant->id, $agentId, $productId, 1);

        $result = app(ExportOrdersAction::class)->execute($tenant->id, $agentId);

        $this->assertSame('completed', $result['operation']->status);
        $this->assertSame(2, $result['operation']->totalRows);
    }

    private function createStockedProduct(int $tenantId, int $quantity): int
    {
        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-'.uniqid(), 1000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, $quantity));

        return $product->id;
    }

    private function placeOrder(int $tenantId, int $agentId, int $productId, int $quantity, ?int $customerId = null): OrderData
    {
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, $quantity);

        return app(PlaceOrderAction::class)->execute($tenantId, $agentId, $cart->id, customerId: $customerId);
    }

    private function setOrderCreatedAt(int $orderId, string $createdAt): void
    {
        DB::table('orders')->where('id', $orderId)->update(['created_at' => $createdAt]);
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Bulk Export Assistant', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return $agent->id;
    }
}
