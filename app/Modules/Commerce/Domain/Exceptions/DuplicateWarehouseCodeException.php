<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Not in the original request's exception list — added unprompted, same
 * reasoning DuplicateVariantAttributeException was (§7.21, HANDOFF §3
 * pattern #12): `warehouses.code` has a real DB-level
 * unique(tenant_id, code) constraint (see that migration), and
 * CreateWarehouseAction needs a real 409 for a duplicate code rather than
 * letting a raw uniqueness violation surface as an unhandled 500.
 */
final class DuplicateWarehouseCodeException extends RuntimeException implements ConflictExceptionInterface
{
}
