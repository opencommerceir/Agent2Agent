<?php

namespace App\Modules\Commerce\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Not in the original request's list of 3 exceptions — added unprompted
 * for the same reason CRM's TagNotFoundException/Finance's
 * OrderNotFoundException were (HANDOFF §3 pattern #12):
 * GenerateVariantCombinationsAction validates each given attribute id
 * against this tenant's own VariantAttributeRepositoryInterface, and a
 * genuinely unknown/cross-tenant id needs a real 404, not a raw
 * foreign-key failure or a silently-empty combination set.
 */
final class VariantAttributeNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
