<?php

namespace App\Domains\Nexus\Llm\Domain\Repositories;

use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling). No update()/delete() —
 * the ledger is append-only (LLMUsageLog's own docblock). Aggregation
 * (budget sums) deliberately lives in Infrastructure\Queries\LLMUsageQuery,
 * not here — same reasoning RevenueQuery keeps raw aggregate SQL out of a
 * repository interface.
 */
interface LLMUsageLogRepositoryInterface
{
    public function save(LLMUsageLog $log): LLMUsageLog;

    /**
     * @return list<LLMUsageLog>
     */
    public function findByBusinessId(int $businessId): array;
}
