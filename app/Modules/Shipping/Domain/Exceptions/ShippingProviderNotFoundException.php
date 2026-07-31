<?php

namespace App\Modules\Shipping\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when `ShippingProviderRegistry::getProvider()` is asked for an
 * unregistered provider name. Implements `NotFoundExceptionInterface` for
 * a real 404 — a deliberate improvement over Commerce's own
 * `ConnectorRegistry`, which today throws a plain `InvalidArgumentException`
 * (422) for the equivalent "unknown connector name" case; not being
 * changed there retroactively, just not repeated here.
 */
final class ShippingProviderNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
