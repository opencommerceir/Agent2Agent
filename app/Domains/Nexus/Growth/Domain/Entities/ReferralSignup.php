<?php

namespace App\Domains\Nexus\Growth\Domain\Entities;

use App\Domains\Nexus\Growth\Domain\ValueObjects\ReferralSignupStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One row per referee — a Business can be referred at most once
 * (unique referee_business_id), recorded the moment it registers with a
 * valid referral code (RecordReferralSignupAction), and only ever
 * transitions Pending -> Rewarded, once, when the referee is Verified
 * (GrantReferralRewardOnBusinessVerifiedListener). Framework-free (Domain
 * Layer Rules). Storing the referrer's id directly (not re-resolving it from
 * the code at reward time) means a referrer changing/losing their code
 * later can never retroactively break an already-recorded signup — the
 * same "denormalize the id you actually need" reasoning Escrow's own
 * businessAId/businessBId already documents.
 */
final class ReferralSignup
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $referrerBusinessId,
        private readonly int $refereeBusinessId,
        private readonly string $referralCode,
        private ReferralSignupStatus $status,
        private ?DateTimeImmutable $rewardedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(int $referrerBusinessId, int $refereeBusinessId, string $referralCode): self
    {
        if ($referrerBusinessId === $refereeBusinessId) {
            throw new InvalidArgumentException('A Business cannot refer itself.');
        }

        return new self(
            id: null,
            referrerBusinessId: $referrerBusinessId,
            refereeBusinessId: $refereeBusinessId,
            referralCode: $referralCode,
            status: ReferralSignupStatus::Pending,
            rewardedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function reward(): void
    {
        if ($this->status !== ReferralSignupStatus::Pending) {
            return;
        }

        $this->status = ReferralSignupStatus::Rewarded;
        $this->rewardedAt = new DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status === ReferralSignupStatus::Pending;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function referrerBusinessId(): int
    {
        return $this->referrerBusinessId;
    }

    public function refereeBusinessId(): int
    {
        return $this->refereeBusinessId;
    }

    public function referralCode(): string
    {
        return $this->referralCode;
    }

    public function status(): ReferralSignupStatus
    {
        return $this->status;
    }

    public function rewardedAt(): ?DateTimeImmutable
    {
        return $this->rewardedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
