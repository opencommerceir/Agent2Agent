<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use App\Domains\Nexus\Llm\Application\Services\LLMProviderRegistry;
use App\Domains\Nexus\Llm\Application\Services\LLMSettingsService;
use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderNotFoundException;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Infrastructure\Queries\LLMUsageQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard, same boundary Phase 1/M1 drew for
 * `User`/`UserRole` and Phase 3/M5 already followed for
 * NexusMarginSettingsController) — docs/nexus-roadmap.md Phase 4's actual
 * deliverable: "Admin can switch LLM without code changes, with one
 * click." Thin — LLMSettingsService/LLMProviderRegistry/LLMUsageQuery are
 * already the right Application-layer shapes, so this depends on them
 * directly rather than wrapping pass-through Actions around them, same
 * justification NexusMarginSettingsController's own docblock already
 * gives.
 */
class NexusLlmSettingsController extends Controller
{
    /**
     * A trivial, fixed ping — cheap enough to never matter for cost, but a
     * real round-trip through the provider's own API, proving the
     * credentials/endpoint actually work.
     */
    private const TEST_CONNECTION_MESSAGE = [['role' => 'user', 'content' => 'ping']];

    public function __construct(
        private readonly LLMSettingsService $llmSettings,
        private readonly LLMProviderRegistry $providers,
        private readonly LLMUsageQuery $usage,
        private readonly LLMUsageLogRepositoryInterface $usageLogs,
        private readonly MarginSettingsService $margin,
    ) {
    }

    public function index(): View
    {
        $featureProviders = [];

        foreach (LLMFeature::cases() as $feature) {
            $featureProviders[$feature->value] = $this->llmSettings->providerForFeature($feature);
        }

        $dailyBudgetPerAgentIrt = $this->llmSettings->dailyBudgetPerAgentIrt();
        $monthlyBudgetPerBusinessIrt = $this->llmSettings->monthlyBudgetPerBusinessIrt();
        $usdToIrtRate = (float) config('nexus.platform.llm.cost_control.usd_to_irt_rate', 0);

        return view('dashboard.nexus.llm-settings.index', [
            'features' => LLMFeature::cases(),
            'featureProviders' => $featureProviders,
            'registeredProviders' => $this->providers->registered(),
            'fallbackChain' => $this->llmSettings->fallbackChain(),
            'dailyBudgetPerAgentIrt' => $dailyBudgetPerAgentIrt,
            'monthlyBudgetPerBusinessIrt' => $monthlyBudgetPerBusinessIrt,
            'isOverBudget' => $this->usage->anyAgentOverDailyBudget($dailyBudgetPerAgentIrt, $usdToIrtRate)
                || $this->usage->anyBusinessOverMonthlyBudget($monthlyBudgetPerBusinessIrt, $usdToIrtRate),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'feature_provider' => ['required', 'array'],
            'feature_provider.*' => ['required', 'string'],
            'fallback_chain' => ['nullable', 'string'],
            'daily_budget_per_agent_irt' => ['required', 'integer', 'min:0'],
            'monthly_budget_per_business_irt' => ['required', 'integer', 'min:0'],
        ]);

        try {
            foreach ($data['feature_provider'] as $featureValue => $providerId) {
                $this->llmSettings->setFeatureProvider(LLMFeature::from($featureValue), $providerId);
            }

            $chain = array_values(array_filter(array_map('trim', explode(',', (string) ($data['fallback_chain'] ?? '')))));
            $this->llmSettings->setFallbackChain($chain);

            $this->llmSettings->setCostControl(
                (int) $data['daily_budget_per_agent_irt'],
                (int) $data['monthly_budget_per_business_irt'],
            );
        } catch (LLMProviderNotFoundException $e) {
            return back()->withErrors(['provider' => $e->getMessage()])->withInput();
        }

        // llm-strategy.md §8's "log every provider change with admin user
        // ID and timestamp" — no separate AuditLog table needed, same
        // reasoning CreditTransaction's own docblock already gives for why
        // one doesn't exist in this codebase.
        Log::info('nexus.llm_settings.changed', ['admin_id' => auth()->id(), 'at' => now()->toIso8601String()]);

        return redirect()->route('dashboard.nexus.llm-settings.index')->with('status', t('messages.nexus.admin.llm_settings.saved'));
    }

    /**
     * Never routed through LLMRouter/LLMBudgetGuard on purpose (Phase 4's
     * decision (e)) — an admin must be able to verify a provider works
     * even mid-outage or over budget, since that's exactly the situation
     * this button exists to diagnose. Still logged to LLMUsageLog
     * (business_id/agent_id both null, feature = 'admin_test_connection')
     * since a paid-provider ping genuinely costs real money.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $data = $request->validate(['provider' => ['required', 'string']]);
        $providerId = $data['provider'];

        try {
            $provider = $this->providers->get($providerId);
        } catch (LLMProviderNotFoundException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
        }

        $startedAt = microtime(true);

        try {
            $response = $provider->chat(self::TEST_CONNECTION_MESSAGE, ['max_tokens' => 5]);
        } catch (LLMProviderRequestFailedException $e) {
            $this->usageLogs->save(LLMUsageLog::record(
                businessId: null,
                agentId: null,
                feature: 'admin_test_connection',
                provider: $providerId,
                model: 'unknown',
                promptTokens: 0,
                completionTokens: 0,
                realCostUsd: 0.0,
                chargedCostUsd: 0.0,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                fromFallback: false,
                success: false,
                errorMessage: $e->getMessage(),
            ));

            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        $chargedCostUsd = $response->estimatedCost * (1 + $this->margin->llmCostMarkupPercent() / 100);

        $this->usageLogs->save(LLMUsageLog::record(
            businessId: null,
            agentId: null,
            feature: 'admin_test_connection',
            provider: $providerId,
            model: $response->model,
            promptTokens: $response->promptTokens,
            completionTokens: $response->completionTokens,
            realCostUsd: $response->estimatedCost,
            chargedCostUsd: $chargedCostUsd,
            latencyMs: (int) round($response->latencyMs),
            fromFallback: false,
            success: true,
            errorMessage: null,
        ));

        return response()->json(['success' => true, 'latencyMs' => $response->latencyMs]);
    }
}
