<?php

namespace App\Domains\Nexus\Llm\Application\Services;

use App\Domains\Nexus\Admin\Domain\Entities\PlatformSetting;
use App\Domains\Nexus\Admin\Domain\Repositories\PlatformSettingRepositoryInterface;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderNotFoundException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use Illuminate\Support\Facades\Cache;

/**
 * The Phase 4 hot-reload mechanism — structural copy of
 * App\Domains\Nexus\Admin\Application\Services\MarginSettingsService (that
 * class's own docblock explains the general shape: no restart or
 * `php artisan config:cache` needed, unlike Laravel's own static
 * `config()`). Reuses the same generic `nexus_platform_settings` key/value
 * table as-is — no new migration, only new keys
 * (`llm.feature_provider.*`, `llm.fallback_chain`,
 * `llm.cost_control.*`), namespaced under their own cache prefix so a
 * `Cache::forget()` here never touches margin.* keys or vice versa.
 *
 * `set*()` validates provider ids against LLMProviderRegistry::registered()
 * before persisting — llm-strategy.md §8's "validate provider IDs" on
 * save, so an admin typo can never silently brick routing.
 */
final class LLMSettingsService
{
    private const CACHE_PREFIX = 'nexus.llm_setting.';

    public function __construct(
        private readonly PlatformSettingRepositoryInterface $settings,
        private readonly LLMProviderRegistry $providers,
    ) {
    }

    public function providerForFeature(LLMFeature $feature): string
    {
        return $this->getString(
            "feature_provider.{$feature->value}",
            fn () => config("nexus.platform.llm.feature_providers.{$feature->value}"),
        );
    }

    /**
     * @return list<string>
     */
    public function fallbackChain(): array
    {
        $chain = $this->getString(
            'fallback_chain',
            fn () => implode(',', config('nexus.platform.llm.fallback_chain', [])),
        );

        return array_values(array_filter(explode(',', $chain), fn (string $id) => $id !== ''));
    }

    public function dailyBudgetPerAgentIrt(): int
    {
        return (int) $this->getString(
            'cost_control.daily_budget_per_agent_irt',
            fn () => (string) config('nexus.platform.llm.cost_control.daily_budget_per_agent_irt', 0),
        );
    }

    public function monthlyBudgetPerBusinessIrt(): int
    {
        return (int) $this->getString(
            'cost_control.monthly_budget_per_business_irt',
            fn () => (string) config('nexus.platform.llm.cost_control.monthly_budget_per_business_irt', 0),
        );
    }

    public function setFeatureProvider(LLMFeature $feature, string $providerId): void
    {
        $this->assertProviderExists($providerId);

        $this->set("feature_provider.{$feature->value}", $providerId);
    }

    /**
     * @param list<string> $providerIds
     */
    public function setFallbackChain(array $providerIds): void
    {
        foreach ($providerIds as $providerId) {
            $this->assertProviderExists($providerId);
        }

        $this->set('fallback_chain', implode(',', $providerIds));
    }

    public function setCostControl(int $dailyBudgetPerAgentIrt, int $monthlyBudgetPerBusinessIrt): void
    {
        $this->set('cost_control.daily_budget_per_agent_irt', (string) $dailyBudgetPerAgentIrt);
        $this->set('cost_control.monthly_budget_per_business_irt', (string) $monthlyBudgetPerBusinessIrt);
    }

    private function assertProviderExists(string $providerId): void
    {
        if (! $this->providers->has($providerId)) {
            throw new LLMProviderNotFoundException("No LLM provider registered under [{$providerId}].");
        }
    }

    private function getString(string $key, \Closure $fallback): string
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key, $fallback) {
            $setting = $this->settings->findByKey("llm.{$key}");

            return $setting !== null ? $setting->value() : (string) $fallback();
        });
    }

    private function set(string $key, string $value): void
    {
        $fullKey = "llm.{$key}";
        $existing = $this->settings->findByKey($fullKey);
        $setting = $existing ?? PlatformSetting::set($fullKey, $value);

        if ($existing) {
            $existing->update($value);
        }

        $this->settings->save($setting);

        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
