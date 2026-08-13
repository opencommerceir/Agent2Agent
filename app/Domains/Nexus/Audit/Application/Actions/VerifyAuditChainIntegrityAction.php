<?php

namespace App\Domains\Nexus\Audit\Application\Actions;

use App\Domains\Nexus\Audit\Domain\Entities\AuditLogEntry;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;

/**
 * Walks the full chain in sequence order and, for every entry, recomputes
 * its hash purely from its own persisted fields plus the persisted
 * prevHash (AuditLogEntry::computeHash() — the exact same canonical
 * payload used at write time), then checks two things: the recomputed
 * hash matches the stored entryHash, and the stored prevHash matches the
 * previous entry's actual entryHash. Either mismatch means a row was
 * edited, deleted, or reordered after the fact. Trusts nothing about the
 * persisted `status`/`entry_hash` columns themselves — every other read
 * path in this domain (repository hydration, the admin listing) does
 * trust them; this is the one place that doesn't.
 */
final class VerifyAuditChainIntegrityAction
{
    public function __construct(
        private readonly AuditLogEntryRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array{intact: bool, checkedCount: int, brokenAtSequence: int|null}
     */
    public function execute(): array
    {
        $entries = $this->repository->allOrderedBySequence();
        $expectedPrevHash = AuditLogEntry::GENESIS_HASH;

        foreach ($entries as $entry) {
            if ($entry->prevHash() !== $expectedPrevHash) {
                return $this->broken($entry->sequence(), count($entries));
            }

            $recomputedHash = AuditLogEntry::computeHash(
                $entry->sequence(),
                $entry->prevHash(),
                $entry->capabilityName(),
                $entry->businessId(),
                $entry->coreAgentId(),
                $entry->status(),
                $entry->inputSummary(),
                $entry->executionTimeMs(),
                $entry->createdAt(),
            );

            if ($recomputedHash !== $entry->entryHash()) {
                return $this->broken($entry->sequence(), count($entries));
            }

            $expectedPrevHash = $entry->entryHash();
        }

        return ['intact' => true, 'checkedCount' => count($entries), 'brokenAtSequence' => null];
    }

    /**
     * @return array{intact: bool, checkedCount: int, brokenAtSequence: int|null}
     */
    private function broken(int $sequence, int $checkedCount): array
    {
        return ['intact' => false, 'checkedCount' => $checkedCount, 'brokenAtSequence' => $sequence];
    }
}
