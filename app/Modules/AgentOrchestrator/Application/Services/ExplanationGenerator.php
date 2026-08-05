<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;
use App\Modules\AgentOrchestrator\Domain\Services\ExplanationGeneratorInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;

/**
 * The one `ExplanationGeneratorInterface` implementation (Phase 6, Stage 6,
 * §7.31) — pure Markdown-ish string formatting, no LLM call, no
 * Repository. Every fact it renders already lives on the given
 * `ReasoningTrace`.
 */
final class ExplanationGenerator implements ExplanationGeneratorInterface
{
    public function generate(ReasoningTrace $trace): string
    {
        $label = $trace->reasoningType === ReasoningType::PreExecution ? 'Pre-Execution Reasoning' : 'Post-Execution Reflection';

        $explanation = "\xF0\x9F\xA4\x94 **{$label}**\n\n";
        $explanation .= "**Goal:** {$trace->goalText}\n";
        $explanation .= "**Agent:** {$trace->agentType->value}\n\n";

        $explanation .= "**Thought Process:**\n";
        foreach ($trace->thoughts as $i => $thought) {
            $explanation .= ($i + 1).". {$thought}\n";
        }

        $explanation .= "\n**Decision:** {$trace->decision}\n";
        $explanation .= '**Confidence:** '.number_format($trace->confidenceScore->asPercentage(), 1)."%\n";
        $explanation .= "\n**Explanation:** {$trace->explanation}\n";

        if ($trace->alternatives !== []) {
            $explanation .= "\n**Alternatives Considered:**\n";
            foreach ($trace->alternatives as $alternative) {
                /** @var AlternativePlan $alternative */
                $explanation .= "- {$alternative->plan} (confidence: ".number_format($alternative->confidence->asPercentage(), 1)."%) — {$alternative->reason}\n";
            }
        }

        return $explanation;
    }
}
