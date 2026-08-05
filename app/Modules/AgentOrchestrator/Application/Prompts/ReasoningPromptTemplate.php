<?php

namespace App\Modules\AgentOrchestrator\Application\Prompts;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;

/**
 * Builds the two prompts `LLMReasoningEngine` sends to whichever provider
 * is configured — pure string formatting, the same "no LLM-specific
 * concerns" shape `PlanningPromptTemplate` already establishes (§7.28), one
 * static builder per `ReasoningEngineInterface` method.
 */
final class ReasoningPromptTemplate
{
    /**
     * @param list<ExecutionPattern> $similarPatterns this tenant's own past patterns whose goal keywords match this Goal
     */
    public static function forThinking(Goal $goal, AgentProfile $profile, array $similarPatterns): string
    {
        $similarText = self::formatSimilarPatterns($similarPatterns);

        return <<<PROMPT
        You are an AI agent about to execute a business goal. Think step-by-step before acting — you are not choosing the exact capabilities to call (a separate planning step does that), you are reasoning about the goal itself: what it requires, what has worked before, how confident you are, and what you would do if this specific approach didn't work.

        Goal: {$goal->text}
        Agent persona: {$profile->name} ({$profile->type->value})
        Agent persona description: {$profile->description}

        This tenant's own similar past goals:
        {$similarText}

        Provide structured reasoning covering: the key aspects of this goal, what similar goals you've handled before and what worked or failed, your overall decision/approach in one sentence, 0-3 alternative approaches you considered and rejected (each with your own confidence in it and why you'd reject it), your confidence in your own decision (0.0-1.0), and a short plain-language explanation a human could read.

        Return a JSON object matching the schema you were given, and nothing else.
        PROMPT;
    }

    public static function forReflection(ExecutionResult $result, ReasoningTrace $preReasoning): string
    {
        $stepsText = self::formatSteps($result);

        return <<<PROMPT
        You are an AI agent reviewing a business goal you just finished executing. Reflect honestly on what happened — what went well, what didn't, and what you'd do differently next time.

        Goal: {$result->goal->text}
        Your plan going in: {$preReasoning->decision}
        Your confidence going in: {$preReasoning->confidenceScore->value}

        Outcome: {$result->status} ({$result->summary})
        Steps:
        {$stepsText}

        Provide structured reflection covering: what actually happened and why (your analysis), how confident you now are that this was the right approach in hindsight (0.0-1.0, this can differ from your confidence going in), the single most useful lesson learned for next time, and a short plain-language explanation a human could read.

        Return a JSON object matching the schema you were given, and nothing else.
        PROMPT;
    }

    /**
     * @param list<ExecutionPattern> $similarPatterns
     */
    private static function formatSimilarPatterns(array $similarPatterns): string
    {
        if ($similarPatterns === []) {
            return '(none found — this appears to be a new kind of goal for this tenant)';
        }

        return implode("\n", array_map(
            fn (ExecutionPattern $pattern) => sprintf(
                '- goals like "%s": used %d time(s), %.0f%% success rate, typically calls: %s',
                str_replace('|', '/', $pattern->goalPattern),
                $pattern->usageCount(),
                $pattern->successRate() * 100,
                implode(', ', $pattern->successfulCapabilities()) ?: '(none recorded)',
            ),
            $similarPatterns,
        ));
    }

    private static function formatSteps(ExecutionResult $result): string
    {
        return implode("\n", array_map(
            fn ($step) => sprintf('- %s: %s', $step->capability, $step->status()->value),
            $result->steps,
        ));
    }
}
