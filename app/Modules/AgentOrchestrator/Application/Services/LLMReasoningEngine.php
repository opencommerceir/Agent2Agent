<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Application\Prompts\ReasoningPromptTemplate;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use App\Modules\AgentOrchestrator\Domain\Services\ReasoningEngineInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The real, LLM-backed `ReasoningEngineInterface` implementation (Phase 6,
 * Stage 6, §7.31) — asks a configured LLM provider to think before a Goal
 * is planned and reflect once it has been executed, mirroring `LLMPlanner`'s
 * own shape (§7.28) field-for-field: same `LLMClientInterface::completeStructured()`
 * call, same "similar past history" prompt context (here,
 * `ExecutionPatternRepositoryInterface::findSimilarPatterns()` — the exact
 * method `LearningService::suggestPlan()` itself already calls, not a new,
 * duplicate lookup), same on-any-failure fallback to a deterministic
 * sibling (`SimpleReasoningEngine`, this reasoning-side `DeterministicPlanner`
 * equivalent).
 *
 * `config('agent-orchestrator.reasoning.type')` defaults to `simple`, not
 * `llm` — the identical "safe default, explicit opt-in for real network
 * calls" reasoning `planner.type` already established (§7.28); this class
 * is only ever bound when an operator explicitly sets `REASONING_TYPE=llm`.
 * Once bound, it still never lets a broken/unreachable LLM turn into a hard
 * failure for the caller — `$fallbackEnabled` (default `true`,
 * `config('agent-orchestrator.reasoning.fallback_to_simple')`) controls
 * whether a failure falls back silently or propagates, same as `LLMPlanner`.
 */
final class LLMReasoningEngine implements ReasoningEngineInterface
{
    private const MAX_SIMILAR_PATTERNS = 5;

    public function __construct(
        private readonly LLMClientInterface $llmClient,
        private readonly ExecutionPatternRepositoryInterface $patterns,
        private readonly ReasoningEngineInterface $fallback,
        private readonly bool $fallbackEnabled = true,
    ) {
    }

    public function think(Goal $goal, AgentProfile $profile, int $tenantId): ReasoningTrace
    {
        try {
            $similar = $this->patterns->findSimilarPatterns($tenantId, $goal->text, $goal->agentType, self::MAX_SIMILAR_PATTERNS);
            $prompt = ReasoningPromptTemplate::forThinking($goal, $profile, $similar);

            $response = $this->llmClient->completeStructured($prompt, $this->thinkingSchema());

            $trace = ReasoningTrace::create(
                tenantId: $tenantId,
                agentType: $goal->agentType,
                goalText: $goal->text,
                reasoningType: ReasoningType::PreExecution,
                thoughts: $this->parseThoughts($response),
                alternatives: $this->parseAlternatives($response),
                confidenceScore: $this->parseConfidence($response),
                decision: $this->parseString($response, 'decision'),
                explanation: $this->parseString($response, 'explanation'),
            );

            Log::info('Pre-execution reasoning completed via LLM', ['goal' => $goal->text, 'confidence' => $trace->confidenceScore->value]);

            return $trace;
        } catch (Throwable $e) {
            Log::warning('LLM reasoning engine failed during think()', ['goal' => $goal->text, 'error' => $e->getMessage()]);

            if (! $this->fallbackEnabled) {
                throw $e;
            }

            return $this->fallback->think($goal, $profile, $tenantId);
        }
    }

    public function reflect(ExecutionResult $result, ReasoningTrace $preReasoning, int $tenantId, int $executionId): ReasoningTrace
    {
        try {
            $prompt = ReasoningPromptTemplate::forReflection($result, $preReasoning);

            $response = $this->llmClient->completeStructured($prompt, $this->reflectionSchema());

            $trace = ReasoningTrace::create(
                tenantId: $tenantId,
                agentType: $preReasoning->agentType,
                goalText: $preReasoning->goalText,
                reasoningType: ReasoningType::PostExecution,
                thoughts: $this->parseThoughts($response),
                alternatives: [],
                confidenceScore: $this->parseConfidence($response),
                decision: $this->parseString($response, 'decision'),
                explanation: $this->parseString($response, 'explanation'),
                executionId: $executionId,
            );

            Log::info('Post-execution reflection completed via LLM', ['goal' => $preReasoning->goalText, 'confidence' => $trace->confidenceScore->value]);

            return $trace;
        } catch (Throwable $e) {
            Log::warning('LLM reasoning engine failed during reflect()', ['goal' => $preReasoning->goalText, 'error' => $e->getMessage()]);

            if (! $this->fallbackEnabled) {
                throw $e;
            }

            return $this->fallback->reflect($result, $preReasoning, $tenantId, $executionId);
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return list<string>
     */
    private function parseThoughts(array $response): array
    {
        $thoughts = $response['thoughts'] ?? null;

        if (! is_array($thoughts) || $thoughts === [] || count(array_filter($thoughts, 'is_string')) !== count($thoughts)) {
            throw new RuntimeException('LLM reasoning response did not contain a non-empty "thoughts" array of strings.');
        }

        return array_values($thoughts);
    }

    /**
     * @param array<string, mixed> $response
     * @return list<AlternativePlan>
     */
    private function parseAlternatives(array $response): array
    {
        $alternatives = $response['alternatives'] ?? [];

        if (! is_array($alternatives)) {
            throw new RuntimeException('LLM reasoning response\'s "alternatives" was not an array.');
        }

        return array_values(array_map(function ($alternative) {
            if (! is_array($alternative) || ! isset($alternative['plan'], $alternative['confidence'], $alternative['reason'])) {
                throw new RuntimeException('LLM reasoning response contained a malformed alternative.');
            }

            return AlternativePlan::create((string) $alternative['plan'], (float) $alternative['confidence'], (string) $alternative['reason']);
        }, $alternatives));
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseConfidence(array $response): ConfidenceScore
    {
        $confidence = $response['confidence'] ?? null;

        if (! is_numeric($confidence)) {
            throw new RuntimeException('LLM reasoning response did not contain a numeric "confidence".');
        }

        return ConfidenceScore::fromFloat((float) $confidence);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseString(array $response, string $key): string
    {
        $value = $response[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("LLM reasoning response did not contain a non-empty \"{$key}\".");
        }

        return $value;
    }

    private function thinkingSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'properties' => [
                'thoughts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'alternatives' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'plan' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['plan', 'confidence', 'reason'],
                    ],
                ],
                'confidence' => ['type' => 'number'],
                'decision' => ['type' => 'string'],
                'explanation' => ['type' => 'string'],
            ],
            'required' => ['thoughts', 'confidence', 'decision', 'explanation'],
        ]);
    }

    private function reflectionSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'properties' => [
                'thoughts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'confidence' => ['type' => 'number'],
                'decision' => ['type' => 'string'],
                'explanation' => ['type' => 'string'],
            ],
            'required' => ['thoughts', 'confidence', 'decision', 'explanation'],
        ]);
    }
}
