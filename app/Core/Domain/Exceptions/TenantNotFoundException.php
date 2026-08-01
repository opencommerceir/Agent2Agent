<?php

namespace App\Core\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Not needed anywhere before Phase 4 Stage 5's UpdateTenantAction — every
 * prior Tenant lookup happened in an already-trusted context (a Tenant id
 * freshly returned by CreateTenantAction, or already validated via an
 * Agent's own token). The Dashboard's own "Edit Tenant" page is the first
 * caller that can be given an arbitrary/stale Tenant id.
 */
final class TenantNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
