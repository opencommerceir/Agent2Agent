<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddToCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withSufficientStock_addsItemAndReservesInventory(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        $this->stock($tenant->id, $product->id, 5);

        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 3);

        $this->assertCount(1, $cart->items);
        $this->assertSame(3, $cart->items[0]['quantity']);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(3, $inventory->quantityReserved());
        $this->assertSame(2, $inventory->available());
    }

    public function test_execute_addingSameProductTwice_increasesQuantityInsteadOfDuplicatingLine(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        $this->stock($tenant->id, $product->id, 5);

        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 2);
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 2);

        $this->assertCount(1, $cart->items);
        $this->assertSame(4, $cart->items[0]['quantity']);
    }

    public function test_execute_exceedingAvailableStock_throwsInsufficientInventoryExceptionAndDoesNotOverReserve(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        $this->stock($tenant->id, $product->id, 5);

        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 3);

        $this->expectException(InsufficientInventoryException::class);

        try {
            app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 3);
        } finally {
            $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
            $this->assertSame(3, $inventory->quantityReserved()); // unchanged by the failed attempt
        }
    }

    public function test_execute_forProductWithNoInventoryRecord_throwsInsufficientInventoryException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $this->expectException(InsufficientInventoryException::class);

        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 1);
    }

    public function test_execute_forDraftProduct_throwsProductNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD'); // defaults to draft
        $this->stock($tenant->id, $product->id, 5);

        $this->expectException(ProductNotFoundException::class);

        app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 1);
    }

    private function stock(int $tenantId, int $productId, int $quantity): void
    {
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $productId, $quantity));
    }
}
