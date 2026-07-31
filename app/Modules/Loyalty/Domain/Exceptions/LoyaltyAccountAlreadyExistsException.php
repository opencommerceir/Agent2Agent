<?php

namespace App\Modules\Loyalty\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Not in the original request's exception list — added unprompted for
 * the same reason CRM's TagNotFoundException/Finance's
 * OrderNotFoundException/Workflows' WorkflowLog were (HANDOFF §3 item
 * 12): rule §d.2 ("هر Customer فقط یک LoyaltyAccount در هر tenant")
 * needs a real, explicit conflict response when
 * CreateLoyaltyAccountAction is called a second time for the same
 * Customer, rather than letting `loyalty_accounts`' own
 * unique(tenant_id, customer_id) constraint surface as a raw, unmapped
 * database exception. A duplicate-account attempt is a legitimate
 * business-rule conflict, not a missing resource or a malformed request,
 * so it implements the same ConflictExceptionInterface
 * InsufficientPointsException does (409, not 422/404).
 */
final class LoyaltyAccountAlreadyExistsException extends RuntimeException implements ConflictExceptionInterface
{
}
