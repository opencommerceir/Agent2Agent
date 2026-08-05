<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Services\PatternExtractorInterface;
use DateTimeImmutable;

/**
 * The one `PatternExtractorInterface` implementation (Phase 6, Stage 4,
 * §7.29). `patternFor()`'s own keyword list is a deliberate, documented
 * MVP simplification — the same "real, working, honestly scoped down"
 * precedent `CustomerLifetimeValue`'s own formula already set (HANDOFF
 * §7.18/§8.52) — not NLP/embedding-based goal similarity; a future
 * semantic/vector approach is the natural upgrade path (see
 * `docs/execution-memory.md`'s own "What this is not" section).
 */
final class PatternExtractor implements PatternExtractorInterface
{
    /**
     * The same illustrative domain-keyword vocabulary this stage's own
     * request used — deliberately not derived from each Agent persona's
     * own `AgentProfile::planningRules()` keys, even though those already
     * exist and are arguably more precise: `AgentProfile` is per-persona
     * config, and a pattern learned under one profile's own keyword
     * vocabulary would silently stop matching if that profile's config
     * ever changed. A fixed, profile-independent vocabulary is a stabler
     * foundation for a *learned* signal that is meant to persist across
     * config edits.
     */
    private const KEYWORDS = ['sales', 'revenue', 'inventory', 'customer', 'report'];

    public function extract(ExecutionResult $result, int $tenantId): ?ExecutionPattern
    {
        if (! $result->isSuccessful()) {
            return null;
        }

        $pattern = $this->patternFor($result->goal);

        if ($pattern === 'general') {
            return null;
        }

        return ExecutionPattern::create(
            tenantId: $tenantId,
            goalPattern: $pattern,
            agentType: $result->goal->agentType,
            successfulCapabilities: $result->successfulCapabilities(),
            now: new DateTimeImmutable(),
        );
    }

    public function patternFor(Goal $goal): string
    {
        $text = mb_strtolower($goal->text);
        $found = [];

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                $found[] = $keyword;
            }
        }

        return $found === [] ? 'general' : implode('|', $found);
    }
}
