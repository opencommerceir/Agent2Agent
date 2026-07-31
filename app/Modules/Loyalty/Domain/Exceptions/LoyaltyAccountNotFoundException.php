<?php

namespace App\Modules\Loyalty\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when a LoyaltyAccount is looked up (by id or by customer_id) and
 * either doesn't exist or belongs to a different tenant — findById()/
 * findByCustomer() never leak a cross-tenant row, so a mismatched tenant
 * produces the identical 404 a genuinely nonexistent account would (same
 * "cross-tenant id -> 404, not 403" shape every other module's
 * findById()-based lookup already established, e.g. CRM's
 * `crm.ticket.get`).
 */
final class LoyaltyAccountNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
