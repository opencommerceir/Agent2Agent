<?php

namespace App\Domains\Nexus\Contract\Domain\Entities;

use App\Domains\Nexus\Contract\Domain\ValueObjects\EscrowStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A state-tracking layer over a Contract's deal value — **not** custody of
 * real money movement between two banks. Nexus has no payout integration
 * for either Business (that's Enterprise/Phase 7 territory); "holding"
 * here means the platform's own records treat the deal as pending
 * settlement, so the Revenue Dashboard (Phase 3/M6) can report gross/net
 * figures honestly. Same "state without a real backing mechanism" honesty
 * Contract's own contentHash (a hash, not real PKI signing) already
 * established for this codebase.
 *
 * `businessAId`/`businessBId` are denormalized straight from the Contract
 * (not looked up via ContractRepositoryInterface at authorization time) so
 * ReleaseEscrowAction/DisputeEscrowAction can check party membership
 * entirely on their own — the same self-contained-authorization
 * convention every Negotiation Action already follows.
 *
 * `platformFeePercent`/`platformFeeAmount`/`netAmount` are snapshotted at
 * hold() time (HoldEscrowAction) — "compute once, apply durably later,"
 * the same principle Order/PaymentSession pricing already establishes —
 * so a later admin margin change never silently reprices an
 * already-held escrow.
 */
final class Escrow
{
    /**
     * @var array<string, list<EscrowStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'held' => [EscrowStatus::Released, EscrowStatus::Disputed],
        'disputed' => [EscrowStatus::Refunded],
    ];

    private function __construct(
        private readonly ?int $id,
        private readonly int $contractId,
        private readonly int $negotiationId,
        private readonly int $businessAId,
        private readonly int $businessBId,
        private readonly int $grossAmount,
        private readonly string $currency,
        private readonly float $platformFeePercent,
        private readonly int $platformFeeAmount,
        private readonly int $netAmount,
        private EscrowStatus $status,
        private ?string $disputeReason,
        private readonly DateTimeImmutable $heldAt,
        private ?DateTimeImmutable $releasedAt,
    ) {
    }

    public static function hold(
        int $contractId,
        int $negotiationId,
        int $businessAId,
        int $businessBId,
        int $grossAmount,
        string $currency,
        float $platformFeePercent,
    ): self {
        $platformFeeAmount = (int) round($grossAmount * $platformFeePercent / 100);

        return new self(
            id: null,
            contractId: $contractId,
            negotiationId: $negotiationId,
            businessAId: $businessAId,
            businessBId: $businessBId,
            grossAmount: $grossAmount,
            currency: $currency,
            platformFeePercent: $platformFeePercent,
            platformFeeAmount: $platformFeeAmount,
            netAmount: $grossAmount - $platformFeeAmount,
            status: EscrowStatus::Held,
            disputeReason: null,
            heldAt: new DateTimeImmutable(),
            releasedAt: null,
        );
    }

    public static function reconstruct(
        int $id,
        int $contractId,
        int $negotiationId,
        int $businessAId,
        int $businessBId,
        int $grossAmount,
        string $currency,
        float $platformFeePercent,
        int $platformFeeAmount,
        int $netAmount,
        EscrowStatus $status,
        ?string $disputeReason,
        DateTimeImmutable $heldAt,
        ?DateTimeImmutable $releasedAt,
    ): self {
        return new self(
            id: $id,
            contractId: $contractId,
            negotiationId: $negotiationId,
            businessAId: $businessAId,
            businessBId: $businessBId,
            grossAmount: $grossAmount,
            currency: $currency,
            platformFeePercent: $platformFeePercent,
            platformFeeAmount: $platformFeeAmount,
            netAmount: $netAmount,
            status: $status,
            disputeReason: $disputeReason,
            heldAt: $heldAt,
            releasedAt: $releasedAt,
        );
    }

    public function isParty(int $businessId): bool
    {
        return $businessId === $this->businessAId || $businessId === $this->businessBId;
    }

    public function release(): void
    {
        $this->transitionTo(EscrowStatus::Released);
        $this->releasedAt = new DateTimeImmutable();
    }

    public function dispute(?string $reason): void
    {
        $this->transitionTo(EscrowStatus::Disputed);
        $this->disputeReason = $reason;
    }

    public function refund(): void
    {
        $this->transitionTo(EscrowStatus::Refunded);
    }

    private function transitionTo(EscrowStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot transition Escrow from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function contractId(): int
    {
        return $this->contractId;
    }

    public function negotiationId(): int
    {
        return $this->negotiationId;
    }

    public function businessAId(): int
    {
        return $this->businessAId;
    }

    public function businessBId(): int
    {
        return $this->businessBId;
    }

    public function grossAmount(): int
    {
        return $this->grossAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function platformFeePercent(): float
    {
        return $this->platformFeePercent;
    }

    public function platformFeeAmount(): int
    {
        return $this->platformFeeAmount;
    }

    public function netAmount(): int
    {
        return $this->netAmount;
    }

    public function status(): EscrowStatus
    {
        return $this->status;
    }

    public function disputeReason(): ?string
    {
        return $this->disputeReason;
    }

    public function heldAt(): DateTimeImmutable
    {
        return $this->heldAt;
    }

    public function releasedAt(): ?DateTimeImmutable
    {
        return $this->releasedAt;
    }
}
