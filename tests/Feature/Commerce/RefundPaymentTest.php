<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\GetOrderAction;
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Application\Actions\RefundPaymentAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\PaymentNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_onCompletedPayment_refundsAndRestoresInventoryAndOrderStatus(): void
    {
        [$tenantId, $paymentId, $orderId, $productId] = $this->processAPayment();

        $result = app(RefundPaymentAction::class)->execute($paymentId, $tenantId, 'Customer changed their mind.');

        $this->assertSame('refunded', $result->status);

        $order = app(GetOrderAction::class)->execute($orderId, $tenantId);
        $this->assertSame('refunded', $order->status);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($productId, $tenantId);
        $this->assertSame(10, $inventory->quantityOnHand()); // back to the original stock
    }

    public function test_execute_onAlreadyRefundedPayment_throwsInvalidArgumentException(): void
    {
        [$tenantId, $paymentId] = $this->processAPayment();
        app(RefundPaymentAction::class)->execute($paymentId, $tenantId);

        $this->expectException(InvalidArgumentException::class);

        app(RefundPaymentAction::class)->execute($paymentId, $tenantId);
    }

    public function test_execute_forNonexistentPayment_throwsPaymentNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(PaymentNotFoundException::class);

        app(RefundPaymentAction::class)->execute(999, $tenant->id);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} tenantId, paymentId, orderId, productId
     */
    private function processAPayment(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agentId = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping')->id;

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 10000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenant->id, MemberType::Agent, $agentId, $product->id, 1);

        $result = app(ProcessPaymentAction::class)->execute(
            tenantId: $tenant->id,
            agentId: $agentId,
            cartId: $cart->id,
            paymentMethod: 'credit_card',
            paymentDetails: [],
        );

        return [$tenant->id, $result['payment']->id, $result['order']->id, $product->id];
    }
}
