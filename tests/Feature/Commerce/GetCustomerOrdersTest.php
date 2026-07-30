<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\GetCustomerOrdersAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the one place Customer and Order (two separate aggregates)
 * interact — through explicit ids and each one's own Repository
 * interface only, per this stage's Dependency Inversion request.
 */
class GetCustomerOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returnsOnlyOrdersLinkedToThatCustomer(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agentId = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping')->id;

        $customer = app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Doe', 'jane@example.com');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));

        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 2);
        $order = app(PlaceOrderAction::class)->execute($tenant->id, $agentId, $cart->id, customerId: $customer->id);

        $result = app(GetCustomerOrdersAction::class)->execute($customer->id, $tenant->id);

        $orderIds = collect($result['orders'])->pluck('id');
        $this->assertTrue($orderIds->contains($order->id));
        $this->assertCount(1, $orderIds);
        $this->assertSame($customer->id, $result['orders'][0]['customerId']);
    }

    public function test_execute_forCustomerWithNoOrders_returnsEmptyList(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $customer = app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Doe', 'jane@example.com');

        $result = app(GetCustomerOrdersAction::class)->execute($customer->id, $tenant->id);

        $this->assertSame([], $result['orders']);
    }

    public function test_execute_forNonexistentCustomer_throwsCustomerNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(CustomerNotFoundException::class);

        app(GetCustomerOrdersAction::class)->execute(999, $tenant->id);
    }
}
