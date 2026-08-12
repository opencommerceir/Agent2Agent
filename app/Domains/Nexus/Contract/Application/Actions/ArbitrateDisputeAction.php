<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\DisputeCaseData;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasReleased;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — the actual arbitration decision, resolving both the DisputeCase and
 * its underlying Escrow together. Deliberately does NOT reuse
 * RefundEscrowAction/ReleaseEscrowAction: those enforce authorization
 * shapes that don't apply here (RefundEscrowAction takes no acting
 * business at all; ReleaseEscrowAction restricts to the buyer only) —
 * an arbiter's decision is an admin override of the normal flow, not
 * either party acting for themselves, so this Action talks to
 * EscrowRepositoryInterface directly (Escrow's own ALLOWED_TRANSITIONS
 * already legalized disputed -> Refunded/Released for exactly this,
 * Phase 6/M3).
 *
 * 'release_seller' dispatches EscrowWasReleased itself (arbitration IS
 * the deal reaching a genuinely final state, same as a normal buyer
 * release) so Reviews & Ratings (Phase 6/M1) becomes reachable the same
 * way it would have without a dispute. 'refund_buyer' dispatches nothing
 * — mirrors RefundEscrowAction's own behavior, which never has either.
 */
final class ArbitrateDisputeAction
{
    private const VALID_RESOLUTIONS = ['refund_buyer', 'release_seller'];

    public function __construct(
        private readonly DisputeCaseRepositoryInterface $disputeCases,
        private readonly EscrowRepositoryInterface $escrows,
    ) {
    }

    public function execute(int $disputeCaseId, string $resolution): DisputeCaseData
    {
        if (! in_array($resolution, self::VALID_RESOLUTIONS, true)) {
            throw new InvalidArgumentException("Unknown dispute resolution [{$resolution}].");
        }

        $disputeCase = $this->disputeCases->findById($disputeCaseId);

        if (! $disputeCase) {
            throw new InvalidArgumentException("DisputeCase [{$disputeCaseId}] does not exist.");
        }

        $escrow = $this->escrows->findById($disputeCase->escrowId());

        if (! $escrow) {
            throw new InvalidArgumentException("Escrow [{$disputeCase->escrowId()}] does not exist.");
        }

        if ($resolution === 'refund_buyer') {
            $escrow->refund();
        } else {
            $escrow->release();
        }

        $escrow = $this->escrows->save($escrow);

        if ($resolution === 'release_seller') {
            Event::dispatch(new EscrowWasReleased($escrow));
        }

        $disputeCase->resolve($resolution);

        return DisputeCaseData::fromEntity($this->disputeCases->save($disputeCase));
    }
}
