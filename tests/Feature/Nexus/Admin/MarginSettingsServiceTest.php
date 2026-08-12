<?php

namespace Tests\Feature\Nexus\Admin;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The actual hot-reload mechanism (Phase 3/M5) — proves a fresh install
 * with no admin-set rows falls back to config(), and that set() is
 * visible to the very next get() with no cache-clear command run (the
 * whole point of this service existing instead of writing to
 * config/nexus/platform.php at runtime).
 */
class MarginSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_withNoOverrideRow_fallsBackToConfig(): void
    {
        config(['nexus.platform.margin.transaction_fee_percent' => 0.5]);

        $this->assertSame(0.5, app(MarginSettingsService::class)->transactionFeePercent());
    }

    public function test_set_isImmediatelyVisibleToTheNextGet_withNoCacheClear(): void
    {
        config(['nexus.platform.margin.transaction_fee_percent' => 0.5]);
        $service = app(MarginSettingsService::class);
        // Prime the cache with the config-derived default first, same as
        // any real caller would before an admin ever changes anything.
        $this->assertSame(0.5, $service->transactionFeePercent());

        $service->set('transaction_fee_percent', 1.25);

        $this->assertSame(1.25, $service->transactionFeePercent());
        $this->assertDatabaseHas('nexus_platform_settings', ['key' => 'transaction_fee_percent', 'value' => '1.25']);
    }

    public function test_set_isVisibleToAFreshServiceInstance(): void
    {
        app(MarginSettingsService::class)->set('llm_cost_markup_percent', 42.0);

        // A brand new instance (e.g. a different request resolving the
        // service anew) sees the override too — this isn't just an
        // in-object cache.
        $fresh = app(MarginSettingsService::class);
        $this->assertSame(42.0, $fresh->llmCostMarkupPercent());
    }

    public function test_set_onExistingKey_overwritesRatherThanDuplicating(): void
    {
        $service = app(MarginSettingsService::class);
        $service->set('negotiation_fee_percent', 1.0);
        $service->set('negotiation_fee_percent', 2.0);

        $this->assertSame(2.0, $service->negotiationFeePercent());
        $this->assertDatabaseCount('nexus_platform_settings', 1);
    }
}
