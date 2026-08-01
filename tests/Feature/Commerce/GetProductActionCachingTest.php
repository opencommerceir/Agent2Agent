<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Services\PerformanceMonitor;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\DeleteProductAction;
use App\Modules\Commerce\Application\Actions\GetProductAction;
use App\Modules\Commerce\Application\Actions\UpdateProductAction;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4 Stage 8 (Performance Optimization, §7.20) — GetProductAction's
 * own reference CacheService integration. Confirms: a second read is
 * genuinely a cache hit (no DB query at all, not just "returns the same
 * data"), UpdateProductAction invalidates it so a stale value is never
 * served, and — the real cross-tenant leak this stage's own docblock
 * warns about — a different Tenant requesting the exact same product id
 * never gets Tenant A's cached data.
 */
class GetProductActionCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PerformanceMonitor::class)->reset();
    }

    public function test_execute_secondCall_isARealCacheHitWithNoDatabaseQuery(): void
    {
        $tenantId = app(CreateTenantAction::class)->execute('Acme', 'acme-'.uniqid())->id;
        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 999, 'USD', status: 'active');

        app(GetProductAction::class)->execute($product->id, $tenantId);

        DB::enableQueryLog();
        app(GetProductAction::class)->execute($product->id, $tenantId);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queryCount);
    }

    public function test_execute_afterUpdate_returnsFreshDataNotStaleCache(): void
    {
        $tenantId = app(CreateTenantAction::class)->execute('Acme', 'acme-'.uniqid())->id;
        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 999, 'USD', status: 'active');

        app(GetProductAction::class)->execute($product->id, $tenantId);

        app(UpdateProductAction::class)->execute(
            $product->id,
            $tenantId,
            'Widget Renamed',
            null,
            1999,
            'USD',
            'active',
        );

        $refetched = app(GetProductAction::class)->execute($product->id, $tenantId);

        $this->assertSame('Widget Renamed', $refetched->name);
        $this->assertSame(1999, $refetched->priceAmount);
    }

    public function test_execute_afterDelete_noLongerReturnsTheCachedProduct(): void
    {
        $tenantId = app(CreateTenantAction::class)->execute('Acme', 'acme-'.uniqid())->id;
        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 999, 'USD', status: 'active');

        app(GetProductAction::class)->execute($product->id, $tenantId);
        app(DeleteProductAction::class)->execute($product->id, $tenantId);

        $this->expectException(ProductNotFoundException::class);
        app(GetProductAction::class)->execute($product->id, $tenantId);
    }

    public function test_execute_differentTenantWithSameProductId_neverSeesTheOtherTenantsCachedProduct(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme', 'acme-'.uniqid())->id;
        $tenantB = app(CreateTenantAction::class)->execute('Beta', 'beta-'.uniqid())->id;

        $productA = app(CreateProductAction::class)->execute($tenantA, 'Acme Widget', 'WIDGET-A', 999, 'USD', status: 'active');
        app(GetProductAction::class)->execute($productA->id, $tenantA);

        $this->expectException(ProductNotFoundException::class);
        app(GetProductAction::class)->execute($productA->id, $tenantB);
    }
}
