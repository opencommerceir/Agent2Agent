<?php

namespace App\Modules\Loyalty\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Not in the original request's exception list — added unprompted for
 * the exact reason CRM's own `CustomerNotFoundException` was (see that
 * class's docblock): CreateLoyaltyAccountAction validates a customer_id
 * against Commerce's `CustomerRepositoryInterface` (Dependency Inversion
 * — an Interface, never Commerce's Infrastructure/Model), but throwing
 * Commerce's own concrete exception type from Loyalty's Application
 * layer would mean importing a concrete class from another Domain
 * Module's Domain layer — the cross-module coupling this pattern exists
 * to avoid. Loyalty owns its own exception for the same concept,
 * implementing the same Core marker interface so MCPExceptionHandler
 * still maps it to 404 identically.
 */
final class CustomerNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
