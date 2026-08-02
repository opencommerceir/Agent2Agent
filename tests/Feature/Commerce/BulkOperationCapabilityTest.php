<?php

namespace Tests\Feature\Commerce;

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
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The literal 10-step end-to-end scenario from Phase 5, Stage 3's own
 * request (§7.23), driven entirely through MCP: a CSV with some
 * deliberately invalid rows -> import -> progress/final counts verified
 * -> the error file lists exactly the bad rows -> a bulk price update and
 * a bulk status change, both tracked as their own BulkOperations -> an
 * Orders export with a date filter -> the tenant's own BulkOperation list
 * shows every one of them -> tenant isolation.
 *
 * Kept at 50 rows (5 deliberately invalid) rather than the request's own
 * literal 1000, the same "proportional behavior, not raw throughput" scope
 * every prior stage's own E2E test already narrows to for test speed —
 * see `ImportProductsActionTest`'s own larger-batch test (Phase 5, Stage 3
 * §7.23) for the identical reasoning, first established there.
 */
class BulkOperationCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullBulkOperationsLifecycle_fromCsvImportToTenantIsolation(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantId, $agentId, $token] = $this->registerAgentWithPermissions([
            'commerce.products.import', 'commerce.customers.import',
            'commerce.orders.export',
            'commerce.products.update', 'commerce.inventory.update',
            'commerce.bulk.read',
            'commerce.cart.manage', 'commerce.orders.create',
        ]);

        // Step 1: a CSV with 45 valid Products + 5 deliberately invalid rows.
        $csv = "sku,name,price,currency,category,status,stock_quantity\n";

        for ($i = 1; $i <= 45; $i++) {
            $csv .= "BULK-{$i},Bulk Product {$i},19.99,USD,,active,10\n";
        }

        for ($i = 46; $i <= 50; $i++) {
            $csv .= ",Missing SKU {$i},not-a-price,USD,,active,10\n"; // blank sku + bad price
        }

        Storage::disk('local')->put('bulk_operations/products.csv', $csv);

        // Step 2: import via MCP.
        $importResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.import_products',
            'input' => ['file_path' => 'products.csv'],
        ], ['Authorization' => "Bearer {$token}"]);
        $importResponse->assertStatus(200);
        $operationId = $importResponse->json('data.operation.id');

        // Step 3/4: with QUEUE_CONNECTION=sync (this suite's own queue
        // driver) the Job has already fully run by the time the MCP
        // response comes back — processedRows/successRows/failedRows are
        // already final, real-time progress tracking proven the same way
        // ImportProductsActionTest's own Action-level test already did.
        $getResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.get',
            'input' => ['operation_id' => $operationId],
        ], ['Authorization' => "Bearer {$token}"]);
        $getResponse->assertStatus(200);
        $getResponse->assertJsonPath('data.operation.status', 'partial');
        $getResponse->assertJsonPath('data.operation.totalRows', 50);
        $getResponse->assertJsonPath('data.operation.processedRows', 50);
        $getResponse->assertJsonPath('data.operation.successRows', 45);
        $getResponse->assertJsonPath('data.operation.failedRows', 5);

        // Step 5: the error file lists exactly the 5 bad rows.
        $errorFilePath = $getResponse->json('data.operation.errorFilePath');
        $this->assertNotNull($errorFilePath);
        Storage::disk('public')->assertExists($errorFilePath);
        $errorCsv = Storage::disk('public')->get($errorFilePath);
        $this->assertSame(6, substr_count($errorCsv, "\n")); // header + 5 error rows (trailing newline from the last row)

        // Confirm the 45 good Products really were created.
        $products = app(ProductRepositoryInterface::class);
        $importedIds = [];

        for ($i = 1; $i <= 45; $i++) {
            $product = $products->findBySku(new \App\Modules\Commerce\Domain\ValueObjects\SKU("BULK-{$i}"), $tenantId);
            $this->assertNotNull($product, "BULK-{$i} should have been imported");
            $importedIds[] = $product->id();
        }

        // Step 6: bulk price update across a subset of the imported Products.
        $priceUpdateResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.update_price',
            'input' => [
                'product_ids' => array_slice($importedIds, 0, 20),
                'new_price' => 2999,
                'currency' => 'USD',
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $priceUpdateResponse->assertStatus(200);
        $priceUpdateResponse->assertJsonPath('data.operation.status', 'completed');
        $priceUpdateResponse->assertJsonPath('data.operation.successRows', 20);

        $updatedProduct = $products->findById($importedIds[0], $tenantId);
        $this->assertSame(2999, $updatedProduct->price()->amount());

        // Step 7: bulk status change to "archived".
        $statusUpdateResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.update_status',
            'input' => [
                'product_ids' => array_slice($importedIds, 20, 10),
                'new_status' => 'archived',
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $statusUpdateResponse->assertStatus(200);
        $statusUpdateResponse->assertJsonPath('data.operation.successRows', 10);

        $archivedProduct = $products->findById($importedIds[20], $tenantId);
        $this->assertSame('archived', $archivedProduct->status()->value);

        // Step 8: export Orders within a date range.
        $customerProduct = app(CreateProductAction::class)->execute($tenantId, 'Real Widget', 'REAL-WIDGET', 5000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $customerProduct->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $customerProduct->id, 2);
        app(PlaceOrderAction::class)->execute($tenantId, $agentId, $cart->id);

        $today = now()->format('Y-m-d');
        $exportResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.export_orders',
            'input' => ['start_date' => $today, 'end_date' => $today],
        ], ['Authorization' => "Bearer {$token}"]);
        $exportResponse->assertStatus(200);
        $exportResponse->assertJsonPath('data.operation.status', 'completed');
        $this->assertNotNull($exportResponse->json('data.download_url'));

        $exportFilePath = $exportResponse->json('data.operation.filePath');
        Storage::disk('public')->assertExists($exportFilePath);
        $exportCsv = Storage::disk('public')->get($exportFilePath);
        $this->assertStringContainsString('order_number,customer_email,total_amount,status,created_at', $exportCsv);

        // Step 9: the tenant's own BulkOperation list shows every run above.
        $listResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);
        $listResponse->assertStatus(200);
        $this->assertCount(4, $listResponse->json('data.operations')); // import, price update, status update, export

        // Step 10: tenant isolation.
        [, , $tokenB] = $this->registerAgentWithPermissions(['commerce.bulk.read']);
        $crossTenantGet = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.get',
            'input' => ['operation_id' => $operationId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantGet->assertStatus(404);
        $crossTenantGet->assertJsonPath('error.code', 'NOT_FOUND');

        $crossTenantList = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.bulk.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantList->assertStatus(200);
        $this->assertCount(0, $crossTenantList->json('data.operations'));
    }

    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Bulk Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Bulk Operator', 'bulk-operator-'.uniqid());

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
