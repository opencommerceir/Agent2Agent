<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\UpdateCartItemQuantityAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCartItemQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_increasingQuantity_reservesOnlyTheDelta(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 5));
        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 2);

        $cart = app(UpdateCartItemQuantityAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 4);

        $this->assertSame(4, $cart->items[0]['quantity']);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(4, $inventory->quantityReserved());
    }

    public function test_execute_decreasingQuantity_releasesOnlyTheDelta(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 5));
        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 4);

        $cart = app(UpdateCartItemQuantityAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 1);

        $this->assertSame(1, $cart->items[0]['quantity']);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(1, $inventory->quantityReserved());
        $this->assertSame(4, $inventory->available());
    }

    public function test_execute_increasingBeyondAvailableStock_throwsInsufficientInventoryException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 5));
        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 2);

        $this->expectException(InsufficientInventoryException::class);

        app(UpdateCartItemQuantityAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 10);
    }
}
