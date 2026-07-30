<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_placesConfirmedOrderAndCommitsInventory(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 3);

        $order = app(PlaceOrderAction::class)->execute($tenant->id, $agentId, $cart->id, 'Please gift-wrap.');

        $this->assertSame('confirmed', $order->status);
        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items[0]['quantity']);
        $this->assertSame(5997, $order->subtotalAmount);
        $this->assertSame('Please gift-wrap.', $order->notes);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{5}$/', $order->orderNumber);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(7, $inventory->quantityOnHand());
        $this->assertSame(0, $inventory->quantityReserved());
        $this->assertSame(7, $inventory->available());

        // Cart must be emptied and checked out, not left dangling as still-active.
        $remainingCart = app(CartRepositoryInterface::class)->findActiveByOwner($tenant->id, MemberType::Agent, $agentId);
        $this->assertNull($remainingCart);
    }

    public function test_execute_forEmptyCart_throwsInvalidArgumentException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $emptyCart = app(CartRepositoryInterface::class)->save(Cart::open($tenant->id, MemberType::Agent, 1));

        $this->expectException(InvalidArgumentException::class);

        app(PlaceOrderAction::class)->execute($tenant->id, 1, $emptyCart->id());
    }

    public function test_execute_forCartBelongingToAnotherAgent_throwsCartNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, 1, $product->id, 3);

        $this->expectException(CartNotFoundException::class);

        app(PlaceOrderAction::class)->execute($tenant->id, 2, $cart->id); // agent 2, not the cart's owner (agent 1)
    }

    public function test_execute_forNonexistentCart_throwsCartNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(CartNotFoundException::class);

        app(PlaceOrderAction::class)->execute($tenant->id, 1, 999);
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());

        return app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Shopping Assistant', 'shopping')->id;
    }
}
