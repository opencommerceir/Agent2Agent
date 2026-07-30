<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Application\Actions\UpdateOrderStatusAction;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InvalidOrderStatusException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_movesThroughFulfillmentPipeline(): void
    {
        $order = $this->placeOrder();

        $result = app(UpdateOrderStatusAction::class)->execute($order->id, $order->tenantId, 'shipped');

        $this->assertSame('shipped', $result->status);
    }

    public function test_execute_targetingCancelled_throwsInvalidOrderStatusException(): void
    {
        $order = $this->placeOrder();

        $this->expectException(InvalidOrderStatusException::class);

        app(UpdateOrderStatusAction::class)->execute($order->id, $order->tenantId, 'cancelled');
    }

    public function test_execute_afterDelivered_throwsInvalidOrderStatusException(): void
    {
        $order = $this->placeOrder();
        app(UpdateOrderStatusAction::class)->execute($order->id, $order->tenantId, 'processing');
        app(UpdateOrderStatusAction::class)->execute($order->id, $order->tenantId, 'shipped');
        app(UpdateOrderStatusAction::class)->execute($order->id, $order->tenantId, 'delivered');

        $this->expectException(InvalidOrderStatusException::class);

        app(UpdateOrderStatusAction::class)->execute($order->id, $order->tenantId, 'processing');
    }

    private function placeOrder(): OrderData
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agentId = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping')->id;
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 3);

        return app(PlaceOrderAction::class)->execute($tenant->id, $agentId, $cart->id);
    }
}
