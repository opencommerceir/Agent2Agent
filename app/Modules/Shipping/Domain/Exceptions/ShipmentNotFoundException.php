<?php

namespace App\Modules\Shipping\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when a Shipment is looked up (by id) and either doesn't exist
 * or belongs to a different tenant — findById() never leaks a
 * cross-tenant row, same "cross-tenant id -> 404, not 403" shape every
 * other module's findById()-based lookup already established.
 */
final class ShipmentNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
