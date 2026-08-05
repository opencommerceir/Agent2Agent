<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;

/**
 * Renders one `ReasoningTrace` into a human-readable explanation (Phase 6,
 * Stage 6, §7.31) — pure formatting, never a second reasoning pass; the
 * `ReasoningTrace` handed in already carries every fact this renders, the
 * same "only combines what it's given" shape `WorkflowEvaluator`/
 * `PricingService`/`TemplateRenderer` already establish for a Domain
 * Service interface.
 */
interface ExplanationGeneratorInterface
{
    public function generate(ReasoningTrace $trace): string;
}
