<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\ValueObjects\PricingBreakdown;

final class PricingData
{
    public function __construct(
        public readonly int $subtotalAmount,
        public readonly string $subtotalCurrency,
        public readonly int $taxAmount,
        public readonly int $discountAmount,
        public readonly int $totalAmount,
        public readonly string $totalCurrency,
    ) {
    }

    public static function fromBreakdown(PricingBreakdown $breakdown): self
    {
        return new self(
            subtotalAmount: $breakdown->subtotal->amount(),
            subtotalCurrency: $breakdown->subtotal->currency(),
            taxAmount: $breakdown->tax->amount(),
            discountAmount: $breakdown->discount->amount(),
            totalAmount: $breakdown->total->amount(),
            totalCurrency: $breakdown->total->currency(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subtotalAmount' => $this->subtotalAmount,
            'subtotalCurrency' => $this->subtotalCurrency,
            'taxAmount' => $this->taxAmount,
            'discountAmount' => $this->discountAmount,
            'totalAmount' => $this->totalAmount,
            'totalCurrency' => $this->totalCurrency,
        ];
    }
}
