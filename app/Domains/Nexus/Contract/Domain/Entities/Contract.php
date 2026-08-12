<?php

namespace App\Domains\Nexus\Contract\Domain\Entities;

use DateTimeImmutable;

/**
 * A signed Contract auto-generated once a Negotiation is Accepted
 * (docs/nexus-roadmap.md: "تولید خودکار قرارداد از روی negotiation ...
 * امضای دیجیتال (hash) و خروجی PDF"). $contentHash is a plain sha256 of
 * the frozen $terms snapshot — the one real "digital signature"
 * precedent found in this codebase (AgentToken::hash()), not real PKI
 * signing. $pdfPath is attached after generate() via attachPdf() — PDF
 * rendering/storage is an Infrastructure concern the entity itself
 * doesn't perform, only records the resulting path (mirrors
 * Business::attachLogo() from Phase 1). Framework-free (Domain Layer
 * Rules).
 */
final class Contract
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $negotiationId,
        private readonly int $businessAId,
        private readonly int $businessBId,
        private readonly array $terms,
        private readonly string $contentHash,
        private ?string $pdfPath,
        private readonly DateTimeImmutable $signedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function generate(int $negotiationId, int $businessAId, int $businessBId, array $terms): self
    {
        return new self(
            id: null,
            negotiationId: $negotiationId,
            businessAId: $businessAId,
            businessBId: $businessBId,
            terms: $terms,
            contentHash: hash('sha256', json_encode($terms, JSON_THROW_ON_ERROR)),
            pdfPath: null,
            signedAt: new DateTimeImmutable(),
            createdAt: new DateTimeImmutable(),
        );
    }

    public function attachPdf(string $pdfPath): void
    {
        $this->pdfPath = $pdfPath;
    }

    public function id(): ?int
    {
        return $this->id;
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

    public function terms(): array
    {
        return $this->terms;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function pdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function signedAt(): DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
