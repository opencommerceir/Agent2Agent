<?php

namespace Tests\Feature\Showcase;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proves DemoShowcaseSeeder actually populates a realistic-looking store
 * — real row counts, not just "the seeder ran without throwing." Also
 * proves it's a no-op the second time it runs against an already-seeded
 * Tenant (idempotent by construction, see the seeder's own docblock —
 * ResetDemoShowcaseCommand is what wipes it first for a genuine reseed).
 */
class DemoShowcaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seederPopulatesARealisticStoreForTheDemoTenant(): void
    {
        $this->seed(DemoShowcaseSeeder::class);

        $tenant = app(TenantRepositoryInterface::class)->findBySlug(DemoShowcaseSeeder::TENANT_SLUG);
        $this->assertNotNull($tenant);
        $tenantId = $tenant->id();

        $this->assertSame(40, DB::table('products')->where('tenant_id', $tenantId)->count());
        $this->assertSame(5, DB::table('categories')->where('tenant_id', $tenantId)->count());
        $this->assertSame(2, DB::table('warehouses')->where('tenant_id', $tenantId)->count());
        $this->assertSame(40, DB::table('customers')->where('tenant_id', $tenantId)->count());
        $this->assertSame(10, DB::table('tickets')->where('tenant_id', $tenantId)->count());
        $this->assertGreaterThan(0, DB::table('product_variants')->where('tenant_id', $tenantId)->count());
        $this->assertGreaterThan(0, DB::table('loyalty_accounts')->where('tenant_id', $tenantId)->count());
        $this->assertGreaterThanOrEqual(2, DB::table('coupons')->where('tenant_id', $tenantId)->count());
        $this->assertGreaterThanOrEqual(2, DB::table('discount_rules')->where('tenant_id', $tenantId)->count());

        $this->assertSame(
            1,
            DB::table('notification_templates')
                ->where('tenant_id', $tenantId)
                ->where('type', 'promotion_announcement')
                ->where('channel_type', 'email')
                ->count()
        );

        // 150-300 was the requested range — asserting against the lower
        // bound (rather than the seeder's own exact target of 180) keeps
        // this test honest about what actually matters (a real, non-trivial
        // spread of historical Orders for a sales trend chart to show)
        // without being brittle to the rare seed-time order that legitimately
        // gets skipped (see DemoShowcaseSeeder::seedOrders()'s own catch).
        $orderCount = DB::table('orders')->where('tenant_id', $tenantId)->count();
        $this->assertGreaterThanOrEqual(150, $orderCount);
        $this->assertSame($orderCount, DB::table('payments')->where('tenant_id', $tenantId)->count());

        // Orders must actually be spread across real historical dates, not
        // all stamped "now" — the whole point of the backdating step.
        $distinctOrderDays = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->selectRaw('DATE(created_at) as day')
            ->distinct()
            ->count();
        $this->assertGreaterThan(10, $distinctOrderDays);

        // Pre-seeded Executions — Execution Memory has something real to
        // recall from before a visitor ever sends a first chat message.
        $this->assertGreaterThanOrEqual(2, DB::table('agent_executions')->where('tenant_id', $tenantId)->count());
    }

    public function test_seederIsANoOpWhenTheDemoTenantAlreadyExists(): void
    {
        $this->seed(DemoShowcaseSeeder::class);
        $firstCount = DB::table('products')->count();

        // Running it again must not throw (duplicate slug/SKU/etc.) and
        // must not double the data.
        $this->seed(DemoShowcaseSeeder::class);
        $secondCount = DB::table('products')->count();

        $this->assertSame($firstCount, $secondCount);
        $this->assertSame(1, DB::table('tenants')->where('slug', DemoShowcaseSeeder::TENANT_SLUG)->count());
    }
}
