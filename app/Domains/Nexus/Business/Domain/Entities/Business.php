<?php

namespace App\Domains\Nexus\Business\Domain\Entities;

use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessStatus;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\DataResidencyRegion;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\VerificationStatus;
use DateTimeImmutable;

/**
 * Aggregate root for a registered Business. Every Business owns exactly
 * one Core Tenant (tenant_id) and one Core Organization (organization_id)
 * — "هر Business = یک Tenant جدید" (docs/nexus-roadmap.md, Phase 1) —
 * reusing the existing multi-tenancy boundary rather than inventing a new
 * one. Framework-free by design (Domain Layer Rules).
 *
 * `status` (Phase 6/M4) is deliberately a separate concept from
 * `verificationStatus` (Phase 1's admin KYC gate) — a Business can be
 * Verified AND Suspended at once; fraud revokes standing to transact, it
 * doesn't retroactively un-verify identity. Mirrors Core's own
 * Agent::AgentStatus shape (active/suspended), the only existing
 * suspend/activate precedent in this codebase.
 */
final class Business
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $organizationId,
        private string $nameFa,
        private string $nameEn,
        private BusinessType $type,
        private Industry $industry,
        private VerificationStatus $verificationStatus,
        private ?string $logoPath,
        private ?array $documents,
        private readonly DateTimeImmutable $createdAt,
        private BusinessStatus $status = BusinessStatus::Active,
        private ?DataResidencyRegion $dataResidencyRegion = null,
    ) {
    }

    public static function register(
        int $tenantId,
        int $organizationId,
        string $nameFa,
        string $nameEn,
        BusinessType $type,
        Industry $industry,
        ?string $logoPath = null,
        ?array $documents = null,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            organizationId: $organizationId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            type: $type,
            industry: $industry,
            verificationStatus: VerificationStatus::Pending,
            logoPath: $logoPath,
            documents: $documents,
            createdAt: new DateTimeImmutable(),
            status: BusinessStatus::Active,
        );
    }

    public function verify(): void
    {
        $this->verificationStatus = VerificationStatus::Verified;
    }

    public function suspend(): void
    {
        $this->status = BusinessStatus::Suspended;
    }

    public function reactivate(): void
    {
        $this->status = BusinessStatus::Active;
    }

    public function isActive(): bool
    {
        return $this->status === BusinessStatus::Active;
    }

    public function status(): BusinessStatus
    {
        return $this->status;
    }

    public function updateProfile(string $nameFa, string $nameEn, BusinessType $type, Industry $industry): void
    {
        $this->nameFa = $nameFa;
        $this->nameEn = $nameEn;
        $this->type = $type;
        $this->industry = $industry;
    }

    public function attachLogo(string $logoPath): void
    {
        $this->logoPath = $logoPath;
    }

    public function attachDocuments(array $documents): void
    {
        $this->documents = $documents;
    }

    public function declareDataResidencyRegion(DataResidencyRegion $region): void
    {
        $this->dataResidencyRegion = $region;
    }

    public function dataResidencyRegion(): ?DataResidencyRegion
    {
        return $this->dataResidencyRegion;
    }

    public function isVerified(): bool
    {
        return $this->verificationStatus === VerificationStatus::Verified;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function organizationId(): int
    {
        return $this->organizationId;
    }

    public function nameFa(): string
    {
        return $this->nameFa;
    }

    public function nameEn(): string
    {
        return $this->nameEn;
    }

    public function type(): BusinessType
    {
        return $this->type;
    }

    public function industry(): Industry
    {
        return $this->industry;
    }

    public function verificationStatus(): VerificationStatus
    {
        return $this->verificationStatus;
    }

    public function logoPath(): ?string
    {
        return $this->logoPath;
    }

    public function documents(): ?array
    {
        return $this->documents;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
