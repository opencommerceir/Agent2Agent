<?php

namespace App\Domains\Nexus\Audit\Domain\Entities;

use App\Domains\Nexus\Audit\Domain\ValueObjects\AuditOutcome;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Phase 7/M9's hash-chained Compliance audit trail — the first
 * platform-wide, cross-domain ledger in this codebase, a deliberate,
 * documented reversal of the "no generic AuditLog, that's scope creep"
 * restraint repeated since CreditTransaction's own docblock (Phase 3/M1):
 * justified because the Phase 7 roadmap, for the first time, actually
 * mandates a generic one ("Audit trail پیشرفته (hash-chained)").
 *
 * Extends the existing single-snapshot hashing precedent
 * (AgentToken::hash(), Contract::contentHash) into a real prev-hash ->
 * this-hash chain for the first time in this codebase — every entry's own
 * hash is computed over its own fields PLUS the previous entry's hash, so
 * altering or removing any past row breaks every hash after it. Framework-
 * free (Domain Layer Rules) — plain `hash()`/`json_encode()`.
 */
final class AuditLogEntry
{
    /**
     * The first entry in the chain hashes against this fixed 64-character
     * all-zero string rather than an empty string or null — a stable,
     * unambiguous "nothing came before this" marker any verifier can
     * reproduce without needing to special-case sequence 1.
     */
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(
        private readonly ?int $id,
        private readonly int $sequence,
        private readonly string $prevHash,
        private readonly string $entryHash,
        private readonly string $capabilityName,
        private readonly ?int $businessId,
        private readonly ?int $coreAgentId,
        private readonly AuditOutcome $status,
        private readonly array $inputSummary,
        private readonly int $executionTimeMs,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $sequence,
        string $prevHash,
        string $capabilityName,
        ?int $businessId,
        ?int $coreAgentId,
        AuditOutcome $status,
        array $inputSummary,
        int $executionTimeMs,
    ): self {
        // Fixed to UTC (not app-configured timezone) so the timestamp
        // embedded in the hash is reproducible regardless of server
        // config — VerifyAuditChainIntegrityAction recomputes this same
        // hash later purely from persisted fields and must get an
        // identical string back.
        $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new self(
            id: null,
            sequence: $sequence,
            prevHash: $prevHash,
            entryHash: self::computeHash($sequence, $prevHash, $capabilityName, $businessId, $coreAgentId, $status, $inputSummary, $executionTimeMs, $createdAt),
            capabilityName: $capabilityName,
            businessId: $businessId,
            coreAgentId: $coreAgentId,
            status: $status,
            inputSummary: $inputSummary,
            executionTimeMs: $executionTimeMs,
            createdAt: $createdAt,
        );
    }

    /**
     * Reconstructs a persisted row's own entity, trusting the stored
     * entryHash rather than recomputing it — VerifyAuditChainIntegrityAction
     * is the one place that recomputes and compares, not every hydration.
     */
    public static function fromPersisted(
        int $id,
        int $sequence,
        string $prevHash,
        string $entryHash,
        string $capabilityName,
        ?int $businessId,
        ?int $coreAgentId,
        AuditOutcome $status,
        array $inputSummary,
        int $executionTimeMs,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $sequence, $prevHash, $entryHash, $capabilityName, $businessId, $coreAgentId, $status, $inputSummary, $executionTimeMs, $createdAt);
    }

    /**
     * The exact canonical payload every hash (both at write time and at
     * verify time) is computed over — deterministic given only the row's
     * own persisted fields plus the chain's prevHash, so a verifier never
     * needs anything beyond what's already in the table.
     */
    public static function computeHash(
        int $sequence,
        string $prevHash,
        string $capabilityName,
        ?int $businessId,
        ?int $coreAgentId,
        AuditOutcome $status,
        array $inputSummary,
        int $executionTimeMs,
        DateTimeImmutable $createdAt,
    ): string {
        $canonicalPayload = json_encode([
            'sequence' => $sequence,
            'capabilityName' => $capabilityName,
            'businessId' => $businessId,
            'coreAgentId' => $coreAgentId,
            'status' => $status->value,
            'inputSummary' => $inputSummary,
            'executionTimeMs' => $executionTimeMs,
            'createdAt' => $createdAt->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $prevHash.$canonicalPayload);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function prevHash(): string
    {
        return $this->prevHash;
    }

    public function entryHash(): string
    {
        return $this->entryHash;
    }

    public function capabilityName(): string
    {
        return $this->capabilityName;
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function coreAgentId(): ?int
    {
        return $this->coreAgentId;
    }

    public function status(): AuditOutcome
    {
        return $this->status;
    }

    public function inputSummary(): array
    {
        return $this->inputSummary;
    }

    public function executionTimeMs(): int
    {
        return $this->executionTimeMs;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
