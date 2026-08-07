<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when `PaymentGatewayRegistry::get()` is asked for an
 * unregistered gateway name — mirrors
 * `Shipping\Domain\Exceptions\ShippingProviderNotFoundException` exactly
 * (same Registry shape, HANDOFF §3 pattern #15).
 */
final class PaymentGatewayNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
