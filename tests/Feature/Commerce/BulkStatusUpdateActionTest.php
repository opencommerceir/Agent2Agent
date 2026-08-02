<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\BulkStatusUpdateAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Action+Job-level tests for Bulk Status Update (Phase 5, Stage 3, §7.23).
 * See `BulkPriceUpdateActionTest`'s own docblock for why no polling is
 * needed under the `sync` queue driver.
 */
class BulkStatusUpdateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updatingStatusOfManyProducts_persistsNewStatusOnEveryOne(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $productIds = [];
        for ($i = 0; $i < 10; $i++) {
            $product = app(CreateProductAction::class)->execute($tenant->id, "Widget {$i}", "WIDGET-{$i}", 1000, 'USD', status: 'draft');
            $productIds[] = $product->id;
        }

        $result = app(BulkStatusUpdateAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            productIds: $productIds,
            newStatus: 'active',
        );

        $this->assertSame('completed', $result->status);
        $this->assertSame(10, $result->successRows);
        $this->assertSame(0, $result->failedRows);

        $products = app(ProductRepositoryInterface::class);
        foreach ($productIds as $id) {
            $this->assertSame('active', $products->findById($id, $tenant->id)->status()->value);
        }
    }

    public function test_batchWithNonexistentProductId_endsAsPartial(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1000, 'USD', status: 'draft');

        $result = app(BulkStatusUpdateAction::class)->execute(
            tenantId: $tenant->id,
            createdBy: $agentId,
            productIds: [$product->id, 999999],
            newStatus: 'archived',
        );

        $this->assertSame('partial', $result->status);
        $this->assertSame(1, $result->successRows);
        $this->assertSame(1, $result->failedRows);

        $updated = app(ProductRepositoryInterface::class)->findById($product->id, $tenant->id);
        $this->assertSame('archived', $updated->status()->value);
    }

    /**
     * A bogus status name is a whole-request problem — validated before a
     * BulkOperation is even created, so no Operation record is left behind
     * having "failed every row" for a reason that was knowable up front.
     */
    public function test_invalidStatusName_failsFastWithoutCreatingABulkOperation(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1000, 'USD', status: 'draft');

        $operations = app(BulkOperationRepositoryInterface::class);
        $countBefore = count($operations->listByTenant($tenant->id));

        try {
            app(BulkStatusUpdateAction::class)->execute(
                tenantId: $tenant->id,
                createdBy: $agentId,
                productIds: [$product->id],
                newStatus: 'not-a-real-status',
            );
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $countAfter = count($operations->listByTenant($tenant->id));
        $this->assertSame($countBefore, $countAfter);

        $untouched = app(ProductRepositoryInterface::class)->findById($product->id, $tenant->id);
        $this->assertSame('draft', $untouched->status()->value);
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Bulk Ops Assistant', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return $agent->id;
    }
}
