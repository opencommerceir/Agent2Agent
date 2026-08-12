<?php

namespace App\Domains\Nexus\Growth\Domain\Entities;

use DateTimeImmutable;

/**
 * One referral code per Business (unique business_id, same 1:1-per-business
 * shape as CreditBalance/Agent) — a Business shares this single code with
 * every lead it invites; there is no per-invite code. Framework-free
 * (Domain Layer Rules). Code generation itself (uniqueness-checked
 * Str::random loop) is the Application layer's job (IssueReferralCodeAction)
 * — the entity only validates the shape it's handed, the same division of
 * labor RegisterBusinessAction's own uniqueSlug() already draws for
 * Tenant slugs.
 */
final class ReferralCode
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly string $code,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function issue(int $businessId, string $code): self
    {
        return new self(
            id: null,
            businessId: $businessId,
            code: $code,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
