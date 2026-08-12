<?php

namespace App\Domains\Nexus\Business\Domain\Entities;

use App\Domains\Nexus\Business\Domain\Exceptions\InvalidSuspensionAppealStateException;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAppealStatus;
use DateTimeImmutable;

/**
 * "Auto-suspension همراه با appeal process" (docs/nexus-roadmap.md, Phase
 * 6, Fraud Detection) — the human-recourse half of Phase 6/M4's
 * suspension mechanism. A suspended Business owner's only path back is
 * this Action, reviewed by an admin (ResolveSuspensionAppealAction);
 * there is no automatic reinstatement. State machine mirrors the
 * codebase-wide ALLOWED_TRANSITIONS + transitionTo() guard shape.
 */
final class SuspensionAppeal
{
    /**
     * @var array<string, list<SuspensionAppealStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => [SuspensionAppealStatus::Approved, SuspensionAppealStatus::Denied],
        'approved' => [],
        'denied' => [],
    ];

    private function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly string $message,
        private SuspensionAppealStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $resolvedAt,
    ) {
    }

    public static function submit(int $businessId, string $message): self
    {
        return new self(
            id: null,
            businessId: $businessId,
            message: $message,
            status: SuspensionAppealStatus::Pending,
            createdAt: new DateTimeImmutable(),
            resolvedAt: null,
        );
    }

    public static function reconstruct(
        int $id,
        int $businessId,
        string $message,
        SuspensionAppealStatus $status,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $resolvedAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            message: $message,
            status: $status,
            createdAt: $createdAt,
            resolvedAt: $resolvedAt,
        );
    }

    public function approve(): void
    {
        $this->transitionTo(SuspensionAppealStatus::Approved);
    }

    public function deny(): void
    {
        $this->transitionTo(SuspensionAppealStatus::Denied);
    }

    private function transitionTo(SuspensionAppealStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidSuspensionAppealStateException(
                "SuspensionAppeal cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
        $this->resolvedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function status(): SuspensionAppealStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
