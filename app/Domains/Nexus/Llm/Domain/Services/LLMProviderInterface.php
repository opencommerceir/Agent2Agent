<?php

namespace App\Domains\Nexus\Llm\Domain\Services;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;

/**
 * The outbound port to a single LLM vendor — docs/nexus-roadmap.md Phase 4's
 * "LLMProviderInterface with chat()/estimateCost()/supports()" verbatim.
 * Deliberately not a reuse of App\Modules\AgentOrchestrator's own
 * LLMClientInterface (complete()/completeStructured(), bare string/array
 * return, no cost/token/latency metadata, single global binding) — that
 * interface's shape can't carry what Phase 4's cost tracking and routing
 * need, and AgentOrchestratorServiceProvider already owns it as one active,
 * single-instance binding, not a multi-provider registry. This is a
 * deliberate parallel design, not an "Extend, Don't Rebuild" violation —
 * contrast with Zibal/Stripe reuse in Phase 3/M3, where the existing shape
 * genuinely matched.
 */
interface LLMProviderInterface
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): LLMResponse;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function estimateCost(array $messages): float;

    public function supports(LLMFeature $feature): bool;

    public function getName(): string;
}
