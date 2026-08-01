<?php

namespace Tests\Feature\Core;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Services\PerformanceMonitor;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\GetProductAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 4 Stage 8 (Performance Optimization, §7.20) —
 * performance:benchmark, performance:check-lazy-loading, cache:warm. Each
 * command's own "no tenants yet" graceful path is exercised implicitly by
 * every other Feature test in this codebase that runs against a fresh
 * RefreshDatabase (§7.18's own "no tenants yet" precedent) — these tests
 * focus on the real, populated-database path instead.
 */
class PerformanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PerformanceMonitor::class)->reset();
    }

    public function test_benchmark_withARealTenant_completesSuccessfully(): void
    {
        $tenantId = app(CreateTenantAction::class)->execute('Acme', 'acme-'.uniqid())->id;
        app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 999, 'USD', status: 'active');

        $this->artisan('performance:benchmark')
            ->expectsOutputToContain('Product search')
            ->expectsOutputToContain('KPI calculation')
            ->assertExitCode(0);
    }

    public function test_benchmark_withNoTenants_exitsGracefully(): void
    {
        $this->artisan('performance:benchmark')
            ->expectsOutputToContain('No tenants exist yet')
            ->assertExitCode(0);
    }

    public function test_checkLazyLoading_withNoRepeatedQueryShapes_reportsNoneFound(): void
    {
        $tenantId = app(CreateTenantAction::class)->execute('Acme', 'acme-'.uniqid())->id;
        app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 999, 'USD', status: 'active');

        $this->artisan('performance:check-lazy-loading')
            ->expectsOutputToContain('No repeated')
            ->assertExitCode(0);
    }

    public function test_warmCache_populatesTheProductCacheForEveryTenant(): void
    {
        $tenantId = app(CreateTenantAction::class)->execute('Acme', 'acme-'.uniqid())->id;
        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 999, 'USD', status: 'active');

        $this->artisan('cache:warm')->assertExitCode(0);

        $this->assertTrue(Cache::has(app(GetProductAction::class)->cacheKey($product->id, $tenantId)));
    }

    public function test_warmCache_withNoTenants_reportsZero(): void
    {
        $this->artisan('cache:warm')
            ->expectsOutputToContain('0 tenant(s), 0 product(s)')
            ->assertExitCode(0);
    }
}
