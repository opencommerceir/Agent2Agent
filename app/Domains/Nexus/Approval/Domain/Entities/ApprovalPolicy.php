<?php

namespace App\Domains\Nexus\Approval\Domain\Entities;

use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Phase 7/M4's "Agent -> Manager -> CFO, configurable by deal volume" — one
 * per Business (unique businessId), an ordered chain of ApprovalLevel rungs.
 * A Business with no policy row keeps exactly Phase 2's original single-
 * implicit-human behavior (OpenApprovalRequestForDealAction is a no-op when
 * none exists) — this is purely additive, not a replacement of the
 * Agent-side authority_limits gate in AcceptDealAction, which stays
 * untouched. Framework-free (Domain Layer Rules).
 */
final class ApprovalPolicy
{
    /**
     * @param  list<ApprovalLevel>  $levels
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private array $levels,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param  list<ApprovalLevel>  $levels
     */
    public static function define(int $businessId, array $levels): self
    {
        self::assertLevelsAreValid($levels);

        return new self(
            id: null,
            businessId: $businessId,
            levels: $levels,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param  list<ApprovalLevel>  $levels
     */
    public function redefine(array $levels): void
    {
        self::assertLevelsAreValid($levels);
        $this->levels = $levels;
    }

    /**
     * @param  list<ApprovalLevel>  $levels
     */
    private static function assertLevelsAreValid(array $levels): void
    {
        if ($levels === []) {
            throw new InvalidArgumentException('An ApprovalPolicy must define at least one level.');
        }

        $previousMinAmount = -1;
        foreach ($levels as $level) {
            if (! $level instanceof ApprovalLevel) {
                throw new InvalidArgumentException('Every level must be an ApprovalLevel.');
            }

            if ($level->minAmount < $previousMinAmount) {
                throw new InvalidArgumentException('ApprovalPolicy levels must be ordered by non-decreasing minAmount.');
            }

            $previousMinAmount = $level->minAmount;
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    /**
     * @return list<ApprovalLevel>
     */
    public function levels(): array
    {
        return $this->levels;
    }

    /**
     * The levels whose threshold a deal of this amount actually meets, in
     * order — or, if none do (the deal is below every configured
     * threshold), the single lowest level, so a Negotiation already paused
     * for human approval always has at least one real approver to resolve
     * it.
     *
     * @return list<ApprovalLevel>
     */
    public function levelsRequiredFor(int $dealAmount): array
    {
        $required = array_values(array_filter($this->levels, fn (ApprovalLevel $level) => $dealAmount >= $level->minAmount));

        return $required !== [] ? $required : [$this->levels[0]];
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
