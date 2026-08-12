<?php

namespace App\Domains\Nexus\Growth\Domain\Entities;

use App\Domains\Nexus\Growth\Domain\ValueObjects\InviteStatus;
use DateTimeImmutable;

/**
 * The roadmap's "Agent-Invites-Agent": one outbound lead record per
 * SendAgentInviteAction call. Always carries the inviter's own
 * ReferralCode (M1) — an Invite is really "a referral code, hand-delivered
 * to one named lead by email" rather than a separate reward mechanism, so
 * conversion crediting stays entirely inside the Referral flow already
 * built (RecordReferralSignupAction/GrantReferralRewardOnBusinessVerifiedListener) —
 * this entity only tracks the funnel (sent -> converted), it never grants
 * credit itself. `messageVariant` exists purely for viral analytics'
 * A/B testing (Phase 5/M5), not for behavior — the email content differs by
 * caller-supplied copy, not by anything this entity decides. Framework-free
 * (Domain Layer Rules).
 */
final class Invite
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $inviterBusinessId,
        private readonly string $inviteeName,
        private readonly string $inviteeEmail,
        private readonly string $referralCode,
        private readonly string $messageVariant,
        private InviteStatus $status,
        private ?int $convertedBusinessId,
        private ?DateTimeImmutable $convertedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function send(
        int $inviterBusinessId,
        string $inviteeName,
        string $inviteeEmail,
        string $referralCode,
        string $messageVariant = 'a',
    ): self {
        return new self(
            id: null,
            inviterBusinessId: $inviterBusinessId,
            inviteeName: $inviteeName,
            inviteeEmail: $inviteeEmail,
            referralCode: $referralCode,
            messageVariant: $messageVariant,
            status: InviteStatus::Sent,
            convertedBusinessId: null,
            convertedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function convert(int $convertedBusinessId): void
    {
        if ($this->status === InviteStatus::Converted) {
            return;
        }

        $this->status = InviteStatus::Converted;
        $this->convertedBusinessId = $convertedBusinessId;
        $this->convertedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function inviterBusinessId(): int
    {
        return $this->inviterBusinessId;
    }

    public function inviteeName(): string
    {
        return $this->inviteeName;
    }

    public function inviteeEmail(): string
    {
        return $this->inviteeEmail;
    }

    public function referralCode(): string
    {
        return $this->referralCode;
    }

    public function messageVariant(): string
    {
        return $this->messageVariant;
    }

    public function status(): InviteStatus
    {
        return $this->status;
    }

    public function convertedBusinessId(): ?int
    {
        return $this->convertedBusinessId;
    }

    public function convertedAt(): ?DateTimeImmutable
    {
        return $this->convertedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
