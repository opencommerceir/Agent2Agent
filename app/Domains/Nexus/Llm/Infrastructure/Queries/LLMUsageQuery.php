<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Queries;

/**
 * Raw aggregate SQL over `nexus_llm_usage_logs`, kept out of
 * LLMUsageLogRepositoryInterface on purpose — same reasoning
 * App\Domains\Nexus\Analytics\Infrastructure\Queries\RevenueQuery already
 * documents for keeping aggregate reads off a repository interface meant
 * for entity persistence. Aggregate methods (budget sums for
 * LLMBudgetGuard) are filled in by Phase 4/M6 — this class exists as of
 * M3 only so M4/M5 can depend on its (empty) shape without a later
 * breaking change.
 */
final class LLMUsageQuery
{
}
