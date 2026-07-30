<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\ClearCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_emptiesCartAndReleasesAllReservedInventory(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $productA = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        $productB = app(CreateProductAction::class)->execute($tenant->id, 'Gadget', 'GADGET-1', 999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $productA->id, 5));
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $productB->id, 5));
        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $productA->id, 2);
        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $productB->id, 3);

        $cart = app(ClearCartAction::class)->execute($tenant->id, MemberType::Agent, 1);

        $this->assertCount(0, $cart->items);

        $inventoryA = app(InventoryRepositoryInterface::class)->findByProduct($productA->id, $tenant->id);
        $inventoryB = app(InventoryRepositoryInterface::class)->findByProduct($productB->id, $tenant->id);
        $this->assertSame(0, $inventoryA->quantityReserved());
        $this->assertSame(0, $inventoryB->quantityReserved());
    }
}
