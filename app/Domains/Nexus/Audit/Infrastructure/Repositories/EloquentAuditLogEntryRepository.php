<?php

namespace App\Domains\Nexus\Audit\Infrastructure\Repositories;

use App\Domains\Nexus\Audit\Domain\Entities\AuditLogEntry as AuditLogEntryEntity;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;
use App\Domains\Nexus\Audit\Domain\ValueObjects\AuditOutcome;
use App\Domains\Nexus\Audit\Infrastructure\Models\AuditLogEntry as AuditLogEntryModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * append() is the one place in this codebase that actually needs
 * pessimistic locking. Every other ledger here (CreditTransaction,
 * LLMUsageLog) writes independent rows — order between them never
 * matters. Here, each row's own hash is computed over the immediately
 * preceding row's hash, so two concurrent MCP requests both reading the
 * same tail and appending "next" would silently produce two entries
 * claiming the same prev_hash — a real chain corruption, not just a race
 * on a counter. SELECT ... FOR UPDATE on the current tail row inside a
 * transaction serializes appends without needing a separate lock table.
 */
class EloquentAuditLogEntryRepository implements AuditLogEntryRepositoryInterface
{
    public function append(
        string $capabilityName,
        ?int $businessId,
        ?int $coreAgentId,
        AuditOutcome $status,
        array $inputSummary,
        int $executionTimeMs,
    ): AuditLogEntryEntity {
        return DB::transaction(function () use ($capabilityName, $businessId, $coreAgentId, $status, $inputSummary, $executionTimeMs) {
            $tail = AuditLogEntryModel::query()->orderByDesc('sequence')->lockForUpdate()->first();

            $sequence = $tail ? $tail->sequence + 1 : 1;
            $prevHash = $tail ? $tail->entry_hash : AuditLogEntryEntity::GENESIS_HASH;

            $entry = AuditLogEntryEntity::record(
                sequence: $sequence,
                prevHash: $prevHash,
                capabilityName: $capabilityName,
                businessId: $businessId,
                coreAgentId: $coreAgentId,
                status: $status,
                inputSummary: $inputSummary,
                executionTimeMs: $executionTimeMs,
            );

            $model = new AuditLogEntryModel();
            $model->sequence = $entry->sequence();
            $model->prev_hash = $entry->prevHash();
            $model->entry_hash = $entry->entryHash();
            $model->capability_name = $entry->capabilityName();
            $model->business_id = $entry->businessId();
            $model->core_agent_id = $entry->coreAgentId();
            $model->status = $entry->status()->value;
            $model->input_summary = $entry->inputSummary();
            $model->execution_time_ms = $entry->executionTimeMs();
            // Explicitly persisted (not left to the column's useCurrent()
            // default) — the hash was already computed over this exact
            // PHP-side UTC instant, so the stored row must carry the same
            // value or VerifyAuditChainIntegrityAction's recomputation
            // would never match.
            $model->created_at = $entry->createdAt();
            $model->save();

            return $entry;
        });
    }

    public function allOrderedBySequence(): array
    {
        return AuditLogEntryModel::query()
            ->orderBy('sequence')
            ->get()
            ->map(fn (AuditLogEntryModel $model) => $this->toEntity($model))
            ->all();
    }

    public function latest(int $limit = 100): array
    {
        return AuditLogEntryModel::query()
            ->orderByDesc('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLogEntryModel $model) => $this->toEntity($model))
            ->all();
    }

    public function count(): int
    {
        return AuditLogEntryModel::query()->count();
    }

    private function toEntity(AuditLogEntryModel $model): AuditLogEntryEntity
    {
        return AuditLogEntryEntity::fromPersisted(
            id: $model->id,
            sequence: $model->sequence,
            prevHash: $model->prev_hash,
            entryHash: $model->entry_hash,
            capabilityName: $model->capability_name,
            businessId: $model->business_id,
            coreAgentId: $model->core_agent_id,
            status: AuditOutcome::from($model->status),
            inputSummary: $model->input_summary ?? [],
            executionTimeMs: $model->execution_time_ms,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
