<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CheckInventoryAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers HANDOFF §8.22 (the arithmetic re-check bug) plus a related,
 * previously-undocumented concurrency gap found while designing the fix
 * — see CheckInventoryAction's own docblock and AddToCartAction's own
 * docblock for the full reasoning behind each half of this fix.
 *
 * A true multi-thread race can't be exercised honestly under a
 * single-threaded PHPUnit process against an in-memory SQLite connection
 * (lockForUpdate() is a no-op on SQLite — it only matters on a real
 * server-based DB with concurrent connections). What *can* be verified
 * here, and is: the locked repository method behaves correctly, and the
 * sequential "two Agents effectively competing for the same stock"
 * scenario never lets combined reservations exceed quantityOnHand —
 * the invariant the lock exists to protect once run against MySQL.
 */
class InventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeOrder_forOverHalfOfOnHandStock_nowSucceeds(): void
    {
        // The exact §8.22 scenario: 7 of 10 on-hand reserved, then placed.
        // Under the old authorize()-based re-check (available() = 10-7 = 3
        // checked against the requested 7), this always failed even though
        // the reservation itself was already correct.
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));

        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 7);
        $order = app(PlaceOrderAction::class)->execute($tenant->id, $agentId, $cart->id);

        $this->assertSame('confirmed', $order->status);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(3, $inventory->quantityOnHand());
        $this->assertSame(0, $inventory->quantityReserved());
    }

    public function test_checkInventoryAction_executeCommit_checksOnHandNotAvailable(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        $inventory = Inventory::stock($tenant->id, $product->id, 10);
        $inventory->reserve(new Quantity(7));
        app(InventoryRepositoryInterface::class)->save($inventory);

        $checkInventory = app(CheckInventoryAction::class);

        // available() is only 3 (10 - 7), but on-hand is still 10 — the
        // already-reserved quantity must still authorize against on-hand.
        $this->assertTrue($checkInventory->executeCommit($product->id, $tenant->id, new Quantity(7)));
        $this->assertFalse($checkInventory->executeCommit($product->id, $tenant->id, new Quantity(11)));
    }

    public function test_sequentialReservationsCannotExceedOnHandStock(): void
    {
        // Simulates two Agents effectively racing for the same 10 units:
        // Agent A reserves 7 (succeeds, available drops to 3), Agent B
        // then tries to reserve 5 — must fail, since only 3 remain, and
        // quantityReserved must never exceed quantityOnHand as a result.
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));

        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 7);

        $this->expectException(InsufficientInventoryException::class);

        try {
            app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 2, $product->id, 5);
        } finally {
            $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
            $this->assertSame(7, $inventory->quantityReserved());
            $this->assertLessThanOrEqual($inventory->quantityOnHand(), $inventory->quantityReserved());
        }
    }

    public function test_findByProductForUpdate_returnsSameDataAsFindByProduct(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));

        $repository = app(InventoryRepositoryInterface::class);
        $locked = $repository->findByProductForUpdate($product->id, $tenant->id);

        $this->assertNotNull($locked);
        $this->assertSame(10, $locked->quantityOnHand());
        $this->assertSame(0, $locked->quantityReserved());
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());

        return app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Shopping Assistant', 'shopping')->id;
    }
}
