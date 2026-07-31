<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\CartStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The scheduled `commerce:check-abandoned-carts` command (HANDOFF
 * §8.23/§8.27), run end to end across two Tenants via
 * TenantRepositoryInterface::all() — proves the cross-tenant iteration,
 * not just MarkCartsAbandonedAction in isolation. Backdates `updated_at`
 * with a raw query builder update (bypassing Eloquent's auto-touch)
 * since that's the only way to simulate "idle for 24h" without actually
 * waiting.
 */
class MarkAbandonedCartsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_marksStaleCartsAbandonedAcrossTenantsAndLeavesFreshCartsAlone(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $productA = app(CreateProductAction::class)->execute($tenantA->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA->id, $productA->id, 10));
        $staleCartA = app(AddToCartAction::class)->execute($tenantA->id, MemberType::Agent, 1, $productA->id, 1);
        DB::table('carts')->where('id', $staleCartA->id)->update(['updated_at' => now()->subHours(30)]);

        $tenantB = app(CreateTenantAction::class)->execute('Other Inc', 'other-'.uniqid());
        $productB = app(CreateProductAction::class)->execute($tenantB->id, 'Gadget', 'GADGET-1', 999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantB->id, $productB->id, 10));
        $freshCartB = app(AddToCartAction::class)->execute($tenantB->id, MemberType::Agent, 1, $productB->id, 1);

        $this->artisan('commerce:check-abandoned-carts')->assertExitCode(0);

        $carts = app(CartRepositoryInterface::class);
        $this->assertSame(CartStatus::Abandoned, $carts->findById($staleCartA->id, $tenantA->id)->status());
        $this->assertSame(CartStatus::Active, $carts->findById($freshCartB->id, $tenantB->id)->status());
    }
}
