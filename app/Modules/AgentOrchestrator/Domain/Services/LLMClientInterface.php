<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

/**
 * A thin outbound port over one LLM provider's own chat/completion API —
 * `OpenAIClient`/`ClaudeClient` (Application/Services) are the two real
 * implementations, chosen at bind-time by `config('agent-orchestrator.llm.provider')`
 * (`AgentOrchestratorServiceProvider::register()`). `LLMPlanner` is the
 * only caller; nothing else in this module (or any other) depends on a
 * concrete LLM provider directly — the same "the module that needs
 * something defines the shape of what it needs" port shape
 * `PaymentGatewayInterface`/`ShippingProviderInterface`/`WooCommerceClientInterface`
 * already establish (HANDOFF §3 pattern #10/#15).
 */
interface LLMClientInterface
{
    /**
     * A plain free-text completion — not used by `LLMPlanner` itself
     * today (it always calls `completeStructured()`), but part of this
     * port's own contract for any future caller that only needs prose
     * (e.g. a narrative `summary`, see `docs/agent-orchestrator.md`'s own
     * roadmap).
     *
     * @param array<string, mixed> $options provider-specific overrides (e.g. temperature, max_tokens)
     */
    public function complete(string $prompt, array $options = []): string;

    /**
     * A completion the provider is asked to constrain to a given JSON
     * shape — `$schema` is a JSON Schema document (as a string; see
     * `LLMPlanner::getPlanSchema()`), not a free-form description. Always
     * returns a decoded PHP array (never a raw JSON string) — parsing
     * failures/malformed responses throw `LLMRequestFailedException`
     * (Domain/Exceptions), the same "normalize every provider-specific
     * failure into one exception type" shape `WooCommerceClient` already
     * establishes for its own external API.
     *
     * @param array<string, mixed> $options provider-specific overrides
     * @return array<string, mixed>
     */
    public function completeStructured(string $prompt, string $schema, array $options = []): array;
}
