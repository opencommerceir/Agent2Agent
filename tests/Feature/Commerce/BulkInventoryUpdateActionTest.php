<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\BulkInventoryUpdateAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action+Job-level tests for Bulk Inventory Update (Phase 5, Stage 3,
 * §7.23). See `BulkPriceUpdateActionTest`'s own docblock for why no
 * polling is needed under the `sync` queue driver.
 */
class BulkInventoryUpdateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulkInventoryUpdate_setsExactQuantityForBothExistingAndMissingRows(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $productWithStock = app(CreateProductAction::class)->execute($tenant->id, 'Widget A', 'WIDGET-A', 1000, 'USD', status: 'active');
        $productWithoutStock = app(CreateProductAction::class)->execute($tenant->id, 'Widget B', 'WIDGET-B', 1000, 'USD', status: 'active');

        $inventories = app(InventoryRepositoryInterface::class);
        // Only the first product has a pre-existing Inventory row.
        $inventories->save(Inventory::stock($tenant->id, $productWithStock->id, 50));

        $result = app(BulkInventoryUpdateAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            updates: [
                ['product_id' => $productWithStock->id, 'variant_id' => null, 'quantity' => 75],
                ['product_id' => $productWithoutStock->id, 'variant_id' => null, 'quantity' => 30],
            ],
        );

        $this->assertSame('completed', $result->status);
        $this->assertSame(2, $result->successRows);
        $this->assertSame(0, $result->failedRows);

        $withStock = $inventories->findByProduct($productWithStock->id, $tenant->id);
        $this->assertSame(75, $withStock->quantityOnHand());

        $withoutStock = $inventories->findByProduct($productWithoutStock->id, $tenant->id);
        $this->assertNotNull($withoutStock);
        $this->assertSame(30, $withoutStock->quantityOnHand());
    }

    public function test_batchWithNonexistentProductId_recordsPartialFailureWithoutAbortingRun(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1000, 'USD', status: 'active');

        $result = app(BulkInventoryUpdateAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            updates: [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 12],
                ['product_id' => 999999, 'variant_id' => null, 'quantity' => 5],
            ],
        );

        $this->assertSame('partial', $result->status);
        $this->assertSame(1, $result->successRows);
        $this->assertSame(1, $result->failedRows);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(12, $inventory->quantityOnHand());
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Bulk Ops Assistant', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return $agent->id;
    }
}
