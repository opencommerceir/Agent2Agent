<?php

namespace App\Modules\Finance\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Not part of the original request's exception list — added for the
 * same reason CRM Foundation added `TagNotFoundException` unprompted
 * (HANDOFF §7.7): CreateInvoiceAction needs to reject an unknown/
 * cross-tenant `order_id` with a real 404, and it must be Finance's own
 * exception type, never Commerce's `Domain\Exceptions\OrderNotFoundException`
 * — throwing Commerce's concrete exception class from Finance would mean
 * importing a concrete Domain type from another module (the same
 * coupling CRM's own `CustomerNotFoundException` docblock explains why
 * to avoid), even though depending on Commerce's `OrderRepositoryInterface`
 * itself is fine.
 */
final class OrderNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
