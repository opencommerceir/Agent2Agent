<?php

namespace App\Domains\Nexus\Holding\Domain\Entities;

use App\Domains\Nexus\Holding\Domain\Exceptions\InvalidSubsidiaryStateException;
use App\Domains\Nexus\Holding\Domain\ValueObjects\SubsidiaryStatus;
use DateTimeImmutable;

/**
 * One row per Business invited into a Holding — a genuine lifecycle
 * (invite -> accept/reject -> remove/leave), unlike CoalitionMember's
 * create-once ledger row, so this gets a small guarded transitionTo(),
 * closer in shape to SuspensionAppeal. `remove()` is the single terminal
 * transition reused by three different Actions (parent removes, subsidiary
 * itself leaves, invitee declines) — each Action applies its own
 * authorization check before calling it, same "one entity method, several
 * differently-authorized callers" shape ApprovePendingNegotiationAction/
 * RejectPendingNegotiationAction already use on Negotiation::accept()/
 * reject(). Framework-free (Domain Layer Rules).
 */
final class HoldingSubsidiary
{
    /**
     * @var array<string, list<SubsidiaryStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'invited' => [SubsidiaryStatus::Active, SubsidiaryStatus::Removed],
        'active' => [SubsidiaryStatus::Removed],
        'removed' => [],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $holdingId,
        private readonly int $businessId,
        private SubsidiaryStatus $status,
        private readonly DateTimeImmutable $invitedAt,
        private ?DateTimeImmutable $respondedAt,
    ) {
    }

    public static function invite(int $holdingId, int $businessId): self
    {
        return new self(
            id: null,
            holdingId: $holdingId,
            businessId: $businessId,
            status: SubsidiaryStatus::Invited,
            invitedAt: new DateTimeImmutable(),
            respondedAt: null,
        );
    }

    public function accept(): void
    {
        $this->transitionTo(SubsidiaryStatus::Active);
        $this->respondedAt = new DateTimeImmutable();
    }

    public function remove(): void
    {
        $this->transitionTo(SubsidiaryStatus::Removed);
        $this->respondedAt = new DateTimeImmutable();
    }

    private function transitionTo(SubsidiaryStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidSubsidiaryStateException(
                "HoldingSubsidiary cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function holdingId(): int
    {
        return $this->holdingId;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function status(): SubsidiaryStatus
    {
        return $this->status;
    }

    public function invitedAt(): DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function respondedAt(): ?DateTimeImmutable
    {
        return $this->respondedAt;
    }
}
