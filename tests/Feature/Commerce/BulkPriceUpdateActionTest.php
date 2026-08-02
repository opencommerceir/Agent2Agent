<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\BulkPriceUpdateAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\GetBulkOperationAction;
use App\Modules\Commerce\Domain\Exceptions\BulkOperationNotFoundException;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action+Job-level tests for Bulk Price Update (Phase 5, Stage 3, §7.23).
 * `ProcessBulkUpdateJob` runs synchronously under this suite's `sync`
 * queue driver (phpunit.xml), so by the time
 * `BulkPriceUpdateAction::execute()` returns below, the whole run has
 * already completed — no polling/waiting needed.
 */
class BulkPriceUpdateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updatingPriceOfManyProducts_persistsNewPriceOnEveryOne(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $productIds = [];
        for ($i = 0; $i < 20; $i++) {
            $product = app(CreateProductAction::class)->execute($tenant->id, "Widget {$i}", "WIDGET-{$i}", 1000, 'USD', status: 'active');
            $productIds[] = $product->id;
        }

        $result = app(BulkPriceUpdateAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            productIds: $productIds,
            newPriceAmount: 2500,
            newPriceCurrency: 'USD',
        );

        $this->assertSame('completed', $result->status);
        $this->assertSame(20, $result->totalRows);
        $this->assertSame(20, $result->successRows);
        $this->assertSame(0, $result->failedRows);

        $products = app(ProductRepositoryInterface::class);
        foreach ($productIds as $id) {
            $product = $products->findById($id, $tenant->id);
            $this->assertSame(2500, $product->price()->amount());
            $this->assertSame('USD', $product->price()->currency());
        }
    }

    public function test_batchWithNonexistentProductId_recordsPartialFailureWithoutAbortingRun(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1000, 'USD', status: 'active');

        $result = app(BulkPriceUpdateAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            productIds: [$product->id, 999999],
            newPriceAmount: 3000,
            newPriceCurrency: 'USD',
        );

        $this->assertSame('partial', $result->status);
        $this->assertSame(1, $result->successRows);
        $this->assertSame(1, $result->failedRows);

        $updated = app(ProductRepositoryInterface::class)->findById($product->id, $tenant->id);
        $this->assertSame(3000, $updated->price()->amount());
    }

    /**
     * The explicit "transaction rollback" requirement: a bad id inside a
     * chunk must not roll back the other ids that genuinely succeeded in
     * that same chunk — proof that the per-row try/catch lives INSIDE
     * ProcessBulkUpdateJob's DB::transaction() closure, not outside it.
     */
    public function test_oneBadIdInsideChunk_doesNotRollBackOtherSuccessfulUpdatesInSameChunk(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $productIds = [];
        for ($i = 0; $i < 4; $i++) {
            $product = app(CreateProductAction::class)->execute($tenant->id, "Widget {$i}", "WIDGET-{$i}", 1000, 'USD', status: 'active');
            $productIds[] = $product->id;
        }

        // A bad id sits in the middle of this single (well under 100-item) chunk.
        $ids = [$productIds[0], $productIds[1], 999999, $productIds[2], $productIds[3]];

        $result = app(BulkPriceUpdateAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            productIds: $ids,
            newPriceAmount: 4200,
            newPriceCurrency: 'USD',
        );

        $this->assertSame('partial', $result->status);
        $this->assertSame(4, $result->successRows);
        $this->assertSame(1, $result->failedRows);

        $products = app(ProductRepositoryInterface::class);
        foreach ($productIds as $id) {
            $product = $products->findById($id, $tenant->id);
            $this->assertSame(4200, $product->price()->amount());
        }
    }

    public function test_operationCreatedUnderOneTenant_isNotVisibleToAnotherTenant(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());
        $agentA = $this->registerAgent($tenantA->id);

        $productA = app(CreateProductAction::class)->execute($tenantA->id, 'Widget', 'WIDGET-1', 1000, 'USD', status: 'active');

        $result = app(BulkPriceUpdateAction::class)->execute(
            tenantId: $tenantA->id,
            createdBy: $agentA,
            productIds: [$productA->id],
            newPriceAmount: 5000,
            newPriceCurrency: 'USD',
        );

        $this->expectException(BulkOperationNotFoundException::class);
        app(GetBulkOperationAction::class)->execute($result->id, $tenantB->id);
    }

    public function test_bulkUpdateUnderOneTenant_cannotMutateAnotherTenantsProduct(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());
        $agentB = $this->registerAgent($tenantB->id);

        $productA = app(CreateProductAction::class)->execute($tenantA->id, 'Widget', 'WIDGET-1', 1000, 'USD', status: 'active');

        $result = app(BulkPriceUpdateAction::class)->execute(
            tenantId: $tenantB->id,
            createdBy: $agentB,
            productIds: [$productA->id],
            newPriceAmount: 9999,
            newPriceCurrency: 'USD',
        );

        // Zero successes out of one attempted row -> the whole run is Failed.
        $this->assertSame('failed', $result->status);
        $this->assertSame(0, $result->successRows);
        $this->assertSame(1, $result->failedRows);

        $stillOriginal = app(ProductRepositoryInterface::class)->findById($productA->id, $tenantA->id);
        $this->assertSame(1000, $stillOriginal->price()->amount());
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Bulk Ops Assistant', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return $agent->id;
    }
}
