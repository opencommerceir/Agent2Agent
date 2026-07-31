<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Core\Domain\ValueObjects\MemberType;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched by MarkCartsAbandonedAction (the scheduled
 * `commerce:check-abandoned-carts` command, HANDOFF §8.23/§8.27) after a
 * Cart idle past the configured threshold transitions to
 * CartStatus::Abandoned. Didn't exist before this — `Cart::abandon()`
 * was itself unused scaffolding until now (no code path ever called it),
 * and Workflows' own CartAbandonedListener has been waiting on exactly
 * this event since Phase 3.3 (that Listener's own prior docblock:
 * "Commerce has no Domain Event for 'this Cart has been sitting idle'").
 *
 * Carries only identifiers, not the Cart entity itself — same reasoning
 * `InventoryWasCommitted` already gives: a listener that needs more detail
 * fetches it fresh through CartRepositoryInterface rather than trusting a
 * snapshot that could already be stale by the time it runs.
 */
final class CartWasAbandoned
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $cartId,
        public readonly MemberType $ownerType,
        public readonly int $ownerId,
    ) {
    }
}
