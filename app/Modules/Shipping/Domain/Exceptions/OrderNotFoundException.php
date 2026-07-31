<?php

namespace App\Modules\Shipping\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Not in the original request's exception list — added unprompted for
 * the exact reason Finance's own `OrderNotFoundException` was (see that
 * class's docblock): `CreateShipmentAction` validates an order_id
 * against Commerce's `OrderRepositoryInterface` (Dependency Inversion —
 * an Interface, never Commerce's Infrastructure/Model), but throwing
 * Commerce's own concrete exception type from Shipping's Application
 * layer would mean importing a concrete class from another Domain
 * Module's Domain layer — the cross-module coupling this pattern exists
 * to avoid. Shipping owns its own exception for the same concept,
 * implementing the same Core marker interface so MCPExceptionHandler
 * still maps it to 404 identically.
 */
final class OrderNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
