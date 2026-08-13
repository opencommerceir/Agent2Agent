<?php

namespace App\Domains\Nexus\Admin\Application\Services;

use App\Domains\Nexus\Admin\Domain\Entities\PlatformSetting;
use App\Domains\Nexus\Admin\Domain\Repositories\PlatformSettingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The actual "hot-reload" mechanism the roadmap's Admin Margin Settings
 * requires ("تغییرات به صورت hot-reload بدون نیاز به restart") — no such
 * mechanism existed anywhere in this codebase before Phase 3/M5 (no
 * settings table, no DB-backed config override). Reads go through
 * `Cache::rememberForever()` keyed per setting, falling back to
 * `config('nexus.platform.margin.*')` when no admin override row exists
 * yet (a fresh install works with zero admin action); every write
 * (`set()`) immediately `Cache::forget()`s that same key — genuinely no
 * restart or `php artisan config:cache` needed, unlike Laravel's own
 * static `config()`, which is why this exists instead of just writing to
 * `config/nexus/platform.php` at runtime.
 */
final class MarginSettingsService
{
    private const CACHE_PREFIX = 'nexus.margin_setting.';

    public function __construct(
        private readonly PlatformSettingRepositoryInterface $settings,
    ) {
    }

    public function llmCostMarkupPercent(): float
    {
        return $this->get('llm_cost_markup_percent');
    }

    public function transactionFeePercent(): float
    {
        return $this->get('transaction_fee_percent');
    }

    public function subscriptionMarkupPercent(): float
    {
        return $this->get('subscription_markup_percent');
    }

    public function negotiationFeePercent(): float
    {
        return $this->get('negotiation_fee_percent');
    }

    /**
     * Phase 9/M7 — the platform's cut of a template publisher's earnings
     * when another Business installs their AgentStrategyTemplate. Same
     * reuse-this-service-not-a-new-mechanism rule every prior new fee
     * (transaction_fee_percent, negotiation_fee_percent, ...) already
     * followed here rather than inventing a parallel settings table.
     */
    public function agentTemplateFeePercent(): float
    {
        return $this->get('agent_template_fee_percent');
    }

    public function set(string $key, float $value): void
    {
        $existing = $this->settings->findByKey($key);
        $setting = $existing ?? PlatformSetting::set($key, (string) $value);

        if ($existing) {
            $existing->update((string) $value);
        }

        $this->settings->save($setting);

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    private function get(string $key): float
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key) {
            $setting = $this->settings->findByKey($key);

            return $setting !== null
                ? (float) $setting->value()
                : (float) config("nexus.platform.margin.{$key}", 0.0);
        });
    }
}
