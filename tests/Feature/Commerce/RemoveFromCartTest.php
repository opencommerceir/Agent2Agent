<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\RemoveFromCartAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RemoveFromCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_removesItemAndReleasesReservedInventory(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 5));
        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 3);

        $cart = app(RemoveFromCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id);

        $this->assertCount(0, $cart->items);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(0, $inventory->quantityReserved());
        $this->assertSame(5, $inventory->available());
    }

    public function test_execute_withoutAnActiveCart_throwsCartNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(CartNotFoundException::class);

        app(RemoveFromCartAction::class)->execute($tenant->id, MemberType::Agent, 1, 999);
    }

    public function test_execute_forProductNotInCart_throwsInvalidArgumentException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $productInCart = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $productInCart->id, 5));
        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $productInCart->id, 1);

        $this->expectException(InvalidArgumentException::class);

        app(RemoveFromCartAction::class)->execute($tenant->id, MemberType::Agent, 1, 999);
    }
}
