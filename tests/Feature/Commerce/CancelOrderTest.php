<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CancelOrderAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Application\Actions\UpdateOrderStatusAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InvalidOrderStatusException;
use App\Modules\Commerce\Domain\Exceptions\OrderAlreadyCancelledException;
use App\Modules\Commerce\Domain\Exceptions\OrderNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_restoresInventoryAndMarksOrderCancelled(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 3);
        $placed = app(PlaceOrderAction::class)->execute($tenant->id, $agentId, $cart->id);

        $cancelled = app(CancelOrderAction::class)->execute($placed->id, $tenant->id);

        $this->assertSame('cancelled', $cancelled->status);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
        $this->assertSame(10, $inventory->quantityOnHand());
        $this->assertSame(10, $inventory->available());
    }

    public function test_execute_whenAlreadyCancelled_throwsOrderAlreadyCancelledException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 3);
        $placed = app(PlaceOrderAction::class)->execute($tenant->id, $agentId, $cart->id);
        app(CancelOrderAction::class)->execute($placed->id, $tenant->id);

        $this->expectException(OrderAlreadyCancelledException::class);

        app(CancelOrderAction::class)->execute($placed->id, $tenant->id);
    }

    public function test_execute_afterShipped_throwsInvalidOrderStatusExceptionAndDoesNotRestoreInventory(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 3);
        $placed = app(PlaceOrderAction::class)->execute($tenant->id, $agentId, $cart->id);
        app(UpdateOrderStatusAction::class)->execute($placed->id, $tenant->id, 'processing');
        app(UpdateOrderStatusAction::class)->execute($placed->id, $tenant->id, 'shipped');

        $this->expectException(InvalidOrderStatusException::class);

        try {
            app(CancelOrderAction::class)->execute($placed->id, $tenant->id);
        } finally {
            $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenant->id);
            $this->assertSame(7, $inventory->quantityOnHand()); // unchanged
        }
    }

    public function test_execute_forNonexistentOrder_throwsOrderNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(OrderNotFoundException::class);

        app(CancelOrderAction::class)->execute(999, $tenant->id);
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());

        return app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Shopping Assistant', 'shopping')->id;
    }
}
