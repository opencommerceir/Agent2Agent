<?php

namespace App\Modules\CRM\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * CRM's own "the referenced Customer doesn't exist" exception —
 * deliberately not Commerce's `Domain\Exceptions\CustomerNotFoundException`.
 * CRM validates a customer id through Commerce's
 * `CustomerRepositoryInterface` (Dependency Inversion — an Interface, never
 * Commerce's Infrastructure/Model), but throwing Commerce's own exception
 * class from CRM's Application layer would mean CRM importing a concrete
 * type from another Domain Module's Domain layer, the same kind of
 * cross-module coupling Core's marker-interface mechanism exists to avoid
 * for Core -> Module. Two modules stay decoupled by each owning their own
 * exception type for the same concept, both implementing this shared Core
 * marker interface so MCPExceptionHandler maps either to 404 identically.
 */
final class CustomerNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
