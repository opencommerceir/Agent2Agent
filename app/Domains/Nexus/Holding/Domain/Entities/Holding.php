<?php

namespace App\Domains\Nexus\Holding\Domain\Entities;

use App\Domains\Nexus\Holding\Domain\ValueObjects\HoldingStatus;
use DateTimeImmutable;

/**
 * Phase 7's "Multi-Business Accounts": a parent Business administers a
 * Holding grouping several subsidiary Businesses (HoldingSubsidiary rows).
 * Nothing in Core's Tenant/Organization hierarchy models this — both are
 * strictly 1:1:1 with a Business (Phase 1/M1's own docblock) — so this is a
 * brand-new domain, the same "new domain when nothing existing fits"
 * precedent Growth (Phase 5) and Llm (Phase 4) already established.
 * Framework-free (Domain Layer Rules).
 */
final class Holding
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $parentBusinessId,
        private string $nameFa,
        private string $nameEn,
        private HoldingStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private bool $creditPoolingEnabled = false,
    ) {
    }

    public static function create(int $parentBusinessId, string $nameFa, string $nameEn): self
    {
        return new self(
            id: null,
            parentBusinessId: $parentBusinessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            status: HoldingStatus::Active,
            createdAt: new DateTimeImmutable(),
            creditPoolingEnabled: false,
        );
    }

    /**
     * Phase 7/M2 — the parent Business's own toggle; SpendCreditsForActionAction
     * reads this (via HoldingRepositoryInterface) to decide whether a
     * gated capability call debits the pool instead of the acting
     * Business's own balance.
     */
    public function enableCreditPooling(): void
    {
        $this->creditPoolingEnabled = true;
    }

    public function disableCreditPooling(): void
    {
        $this->creditPoolingEnabled = false;
    }

    public function creditPoolingEnabled(): bool
    {
        return $this->creditPoolingEnabled;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function parentBusinessId(): int
    {
        return $this->parentBusinessId;
    }

    public function nameFa(): string
    {
        return $this->nameFa;
    }

    public function nameEn(): string
    {
        return $this->nameEn;
    }

    public function status(): HoldingStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
