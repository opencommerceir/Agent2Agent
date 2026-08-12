<?php

namespace App\Domains\Nexus\Llm\Application\Services;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog;
use App\Domains\Nexus\Llm\Domain\Exceptions\AllLLMProvidersFailedException;
use App\Domains\Nexus\Llm\Domain\Exceptions\BudgetLimitExceededException;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderNotFoundException;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;

/**
 * The central integration point docs/nexus-roadmap.md Phase 4 calls
 * "LLMRouter" — resolves the admin-configured provider for a feature
 * (LLMSettingsService, hot-reloaded), calls it, and on failure walks the
 * configured fallback chain (docs/claude/llm-strategy.md §11), skipping a
 * paid candidate when the primary itself was free/local unless
 * `behavior.allow_local_to_paid_fallback` is explicitly true ("never
 * fallback from local to paid automatically unless explicitly allowed").
 * Every attempt — success or failure, primary or fallback — is recorded to
 * LLMUsageLog (Phase 4/M3), including failed ones (`success = false`,
 * `errorMessage` set, cost `0`), so "audit everything" holds even when a
 * whole call fails.
 *
 * If the primary and every surviving fallback candidate fail,
 * AllLLMProvidersFailedException is thrown rather than a fabricated
 * soft-error LLMResponse. docs/nexus-roadmap.md's "use Rule Engine if all
 * LLMs fail (never stop)" is satisfied by whatever domain calls this
 * router catching that exception and falling back to its own existing
 * deterministic logic (e.g.
 * App\Domains\Nexus\Negotiation\Application\Services\NegotiationReasoningService,
 * already unconditionally deterministic and cannot itself fail) — not by
 * this router inventing a second, generic Rule Engine that doesn't
 * otherwise exist in this codebase. No live caller does this yet in
 * Phase 4 (see docs/nexus/nexus_handoff.md's Phase 4 decision on
 * Negotiation rewiring being deliberately out of scope); this contract is
 * proven correct by this class's own tests and Phase 4/M8's manual E2E.
 */
final class LLMRouter
{
    public function __construct(
        private readonly LLMProviderRegistry $providers,
        private readonly LLMSettingsService $settings,
        private readonly LLMUsageLogRepositoryInterface $usageLogs,
        private readonly MarginSettingsService $margin,
        private readonly LLMBudgetGuard $budget,
    ) {
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function route(
        LLMFeature $feature,
        array $messages,
        array $options = [],
        ?int $businessId = null,
        ?int $agentId = null,
    ): LLMResponse {
        $primaryId = $this->settings->providerForFeature($feature);
        $primaryTier = $this->tierOf($primaryId);
        $attempted = [$primaryId];

        try {
            return $this->attempt($primaryId, $feature, $messages, $options, $businessId, $agentId, fromFallback: false);
        } catch (LLMProviderNotFoundException|LLMProviderRequestFailedException|BudgetLimitExceededException $primaryException) {
            if (! (bool) config('nexus.platform.llm.behavior.enable_fallback', true)) {
                throw $primaryException;
            }
        }

        $allowLocalToPaid = (bool) config('nexus.platform.llm.behavior.allow_local_to_paid_fallback', false);

        foreach ($this->settings->fallbackChain() as $candidateId) {
            if (in_array($candidateId, $attempted, true)) {
                continue;
            }

            $attempted[] = $candidateId;

            if ($primaryTier !== 'paid' && $this->tierOf($candidateId) === 'paid' && ! $allowLocalToPaid) {
                continue;
            }

            try {
                return $this->attempt($candidateId, $feature, $messages, $options, $businessId, $agentId, fromFallback: true);
            } catch (LLMProviderNotFoundException|LLMProviderRequestFailedException|BudgetLimitExceededException) {
                continue;
            }
        }

        throw new AllLLMProvidersFailedException(
            "All LLM providers failed for feature [{$feature->value}]: ".implode(', ', $attempted),
        );
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     *
     * @throws LLMProviderNotFoundException
     * @throws LLMProviderRequestFailedException
     * @throws BudgetLimitExceededException
     */
    private function attempt(
        string $providerId,
        LLMFeature $feature,
        array $messages,
        array $options,
        ?int $businessId,
        ?int $agentId,
        bool $fromFallback,
    ): LLMResponse {
        $startedAt = microtime(true);

        try {
            $provider = $this->providers->get($providerId);
            $this->budget->assertWithinBudget($agentId, $businessId, $providerId, $provider->estimateCost($messages));
            $response = $provider->chat($messages, $options);
        } catch (LLMProviderNotFoundException|LLMProviderRequestFailedException|BudgetLimitExceededException $e) {
            $this->recordAttempt(
                businessId: $businessId,
                agentId: $agentId,
                feature: $feature,
                providerId: $providerId,
                model: 'unknown',
                promptTokens: 0,
                completionTokens: 0,
                realCostUsd: 0.0,
                chargedCostUsd: 0.0,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                fromFallback: $fromFallback,
                success: false,
                errorMessage: $e->getMessage(),
            );

            throw $e;
        }

        $chargedCostUsd = $response->estimatedCost * (1 + $this->margin->llmCostMarkupPercent() / 100);

        $this->recordAttempt(
            businessId: $businessId,
            agentId: $agentId,
            feature: $feature,
            providerId: $providerId,
            model: $response->model,
            promptTokens: $response->promptTokens,
            completionTokens: $response->completionTokens,
            realCostUsd: $response->estimatedCost,
            chargedCostUsd: $chargedCostUsd,
            latencyMs: (int) round($response->latencyMs),
            fromFallback: $fromFallback,
            success: true,
            errorMessage: null,
        );

        return $fromFallback ? $response->withFallbackFlag(true) : $response;
    }

    private function recordAttempt(
        ?int $businessId,
        ?int $agentId,
        LLMFeature $feature,
        string $providerId,
        string $model,
        int $promptTokens,
        int $completionTokens,
        float $realCostUsd,
        float $chargedCostUsd,
        int $latencyMs,
        bool $fromFallback,
        bool $success,
        ?string $errorMessage,
    ): void {
        $this->usageLogs->save(LLMUsageLog::record(
            businessId: $businessId,
            agentId: $agentId,
            feature: $feature->value,
            provider: $providerId,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            realCostUsd: $realCostUsd,
            chargedCostUsd: $chargedCostUsd,
            latencyMs: $latencyMs,
            fromFallback: $fromFallback,
            success: $success,
            errorMessage: $errorMessage,
        ));
    }

    private function tierOf(string $providerId): string
    {
        // Unknown provider id defaults to 'paid' — the safer assumption
        // (never silently treat an unrecognized id as free/local, which
        // would bypass the "never local-to-paid" fallback filter's whole
        // purpose).
        return config("nexus.platform.llm.provider_tiers.{$providerId}", 'paid');
    }
}
