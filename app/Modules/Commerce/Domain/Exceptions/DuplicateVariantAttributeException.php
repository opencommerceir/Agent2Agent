<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Not in the original request's list of 3 exceptions — added unprompted
 * (HANDOFF §3 pattern #12): `variant_attributes` has a real
 * `unique(tenant_id, name)` DB constraint, and CreateVariantAttributeAction
 * needs a real 409 for a duplicate name rather than letting a raw DB
 * uniqueness violation surface as an unhandled 500.
 */
final class DuplicateVariantAttributeException extends RuntimeException implements ConflictExceptionInterface
{
}
