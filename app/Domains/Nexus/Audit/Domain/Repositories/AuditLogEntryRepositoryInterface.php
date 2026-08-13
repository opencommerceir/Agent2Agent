<?php

namespace App\Domains\Nexus\Audit\Domain\Repositories;

use App\Domains\Nexus\Audit\Domain\Entities\AuditLogEntry;
use App\Domains\Nexus\Audit\Domain\ValueObjects\AuditOutcome;

interface AuditLogEntryRepositoryInterface
{
    /**
     * Appends the next entry in the chain — the implementation is
     * responsible for reading the current tail and computing
     * sequence/prevHash atomically (see EloquentAuditLogEntryRepository's
     * own docblock), not the caller.
     *
     * @param  array<string, mixed>  $inputSummary
     */
    public function append(
        string $capabilityName,
        ?int $businessId,
        ?int $coreAgentId,
        AuditOutcome $status,
        array $inputSummary,
        int $executionTimeMs,
    ): AuditLogEntry;

    /**
     * The full chain, oldest first — VerifyAuditChainIntegrityAction is
     * the only caller; every other read path uses latest() instead.
     *
     * @return list<AuditLogEntry>
     */
    public function allOrderedBySequence(): array;

    /**
     * @return list<AuditLogEntry> newest first
     */
    public function latest(int $limit = 100): array;

    public function count(): int;
}
