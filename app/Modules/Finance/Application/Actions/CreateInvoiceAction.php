<?php

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Commerce\Domain\Entities\OrderItem;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Finance\Application\DTOs\InvoiceData;
use App\Modules\Finance\Domain\Entities\Invoice;
use App\Modules\Finance\Domain\Entities\InvoiceItem;
use App\Modules\Finance\Domain\Events\InvoiceWasCreated;
use App\Modules\Finance\Domain\Exceptions\OrderNotFoundException;
use App\Modules\Finance\Domain\Repositories\InvoiceRepositoryInterface;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use App\Modules\Finance\Domain\Services\TaxCalculationService;
use App\Modules\Finance\Domain\ValueObjects\InvoiceNumber;
use App\Modules\Finance\Domain\ValueObjects\Money;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Builds an Invoice from an already-placed Commerce Order: fetches the
 * Order (Commerce's own OrderRepositoryInterface — an Interface, never
 * Commerce's Infrastructure/Model classes; see this class's own
 * OrderNotFoundException for why a 404 here is Finance's own exception,
 * not Commerce's), snapshots each OrderItem into a frozen InvoiceItem
 * (looking up the billed Product's current name for the item's
 * description via Commerce's ProductRepositoryInterface — a second,
 * identical cross-module dependency), and computes tax fresh from
 * Finance's own TaxRate configuration for the given region — it does
 * NOT copy whatever tax the Order itself already has stored, since a
 * plain `commerce.order.place` Order has zero tax and even a
 * checkout-processed one may predate any TaxRate being configured.
 *
 * Region resolution is a 2-tier fallback, not the 3-tier chain
 * CommerceTaxRateProvider gives Commerce's own checkout pricing: try the
 * given region, then the tenant's TaxRegion::default() row, and if
 * neither exists, charge zero tax rather than guessing — Commerce's own
 * 9% hardcoded fallback belongs to Commerce's pricing policy, not
 * Finance's invoicing policy, so it is deliberately not reused here.
 */
final class CreateInvoiceAction
{
    private const MAX_INVOICE_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly TaxRateRepositoryInterface $taxRates,
        private readonly TaxCalculationService $taxCalculator,
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(int $tenantId, int $orderId, ?string $region = null): InvoiceData
    {
        $order = $this->orders->findById($orderId, $tenantId);

        if (! $order) {
            throw new OrderNotFoundException("Order [{$orderId}] does not exist.");
        }

        $items = array_map(
            fn (OrderItem $orderItem) => $this->toInvoiceItem($orderItem, $tenantId),
            $order->items(),
        );

        $subtotal = Money::fromAmount($order->subtotal()->amount(), $order->subtotal()->currency());

        $taxRate = null;

        if ($region !== null) {
            $taxRate = $this->taxRates->findByRegion(new TaxRegion($region), $tenantId);
        }

        if ($taxRate === null || ! $taxRate->isActive()) {
            $defaultRate = $this->taxRates->findByRegion(TaxRegion::default(), $tenantId);
            $taxRate = ($defaultRate !== null && $defaultRate->isActive()) ? $defaultRate : null;
        }

        $tax = $taxRate !== null
            ? $this->taxCalculator->calculateTax($subtotal, $taxRate)
            : Money::fromAmount(0, $subtotal->currency());

        $total = $this->taxCalculator->calculateTotal($subtotal, $tax);

        $invoiceNumber = $this->generateUniqueInvoiceNumber($tenantId);

        $invoice = Invoice::create($tenantId, $orderId, $order->customerId(), $invoiceNumber, $items, $subtotal, $tax, $total);
        $invoice = $this->invoices->save($invoice);

        Event::dispatch(new InvoiceWasCreated($invoice));

        return InvoiceData::fromEntity($invoice);
    }

    private function toInvoiceItem(OrderItem $orderItem, int $tenantId): InvoiceItem
    {
        $product = $this->products->findById($orderItem->productId(), $tenantId);
        $description = $product?->name() ?? "Product #{$orderItem->productId()}";

        $unitPrice = Money::fromAmount($orderItem->unitPrice()->amount(), $orderItem->unitPrice()->currency());
        $totalAmount = Money::fromAmount($orderItem->totalAmount(), $orderItem->unitPrice()->currency());

        return InvoiceItem::create($description, $orderItem->quantity()->value(), $unitPrice, $totalAmount);
    }

    private function generateUniqueInvoiceNumber(int $tenantId): InvoiceNumber
    {
        $today = new DateTimeImmutable();

        for ($attempt = 0; $attempt < self::MAX_INVOICE_NUMBER_ATTEMPTS; $attempt++) {
            $invoiceNumber = InvoiceNumber::generate($today, random_int(1, 99999));

            if (! $this->invoices->invoiceNumberExists($invoiceNumber->value(), $tenantId)) {
                return $invoiceNumber;
            }
        }

        throw new RuntimeException('Could not generate a unique invoice number after several attempts.');
    }
}
