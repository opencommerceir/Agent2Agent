<?php

namespace App\Domains\Nexus\Llm\Domain\ValueObjects;

/**
 * The shape docs/claude/llm-strategy.md §1 documents for every
 * LLMProviderInterface::chat() call — token/cost/latency metadata a plain
 * string return (AgentOrchestrator's own LLMClientInterface::complete())
 * doesn't carry, which is exactly why that interface can't be reused here.
 * Framework-free, immutable (Domain Layer Rules).
 *
 * `error` is part of the documented shape but always null in this phase —
 * every real provider failure in this codebase surfaces via a typed
 * exception instead (the same "normalize every failure into one exception"
 * idiom OpenAIClient/ClaudeClient/ZibalPaymentGateway already use), not a
 * soft-error response. Reserved for a future non-throwing caller shape.
 */
final class LLMResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        public readonly float $estimatedCost,
        public readonly float $latencyMs,
        public readonly bool $fromFallback,
        public readonly ?string $error = null,
    ) {
    }

    public static function success(
        string $content,
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
        float $estimatedCost,
        float $latencyMs,
        bool $fromFallback = false,
    ): self {
        return new self(
            content: $content,
            provider: $provider,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $promptTokens + $completionTokens,
            estimatedCost: $estimatedCost,
            latencyMs: $latencyMs,
            fromFallback: $fromFallback,
        );
    }

    public function withFallbackFlag(bool $fromFallback): self
    {
        return new self(
            content: $this->content,
            provider: $this->provider,
            model: $this->model,
            promptTokens: $this->promptTokens,
            completionTokens: $this->completionTokens,
            totalTokens: $this->totalTokens,
            estimatedCost: $this->estimatedCost,
            latencyMs: $this->latencyMs,
            fromFallback: $fromFallback,
            error: $this->error,
        );
    }
}
