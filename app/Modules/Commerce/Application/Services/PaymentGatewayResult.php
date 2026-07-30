<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * PaymentGatewayInterface::charge()'s return shape. An Application-layer
 * supporting type (not a Domain VO) — it's purely integration mechanics
 * (what a gateway responded with), not a business rule.
 */
final class PaymentGatewayResult
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $transactionId,
        public readonly array $rawResponse,
    ) {
    }
}
