<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_persistsCoupon(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $result = app(CreateCouponAction::class)->execute(
            tenantId: $tenant->id,
            code: 'coupon-ab12c',
            discountType: 'percentage',
            discountValue: 10,
        );

        $this->assertNotNull($result->id);
        $this->assertSame('COUPON-AB12C', $result->code);
        $this->assertSame(0, $result->usedCount);
        $this->assertTrue($result->isActive);
        $this->assertDatabaseHas('coupons', [
            'tenant_id' => $tenant->id,
            'code' => 'COUPON-AB12C',
        ]);
    }

    public function test_execute_withDuplicateCodeInSameTenant_throwsInvalidArgumentException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(CreateCouponAction::class)->execute($tenant->id, 'COUPON-AB12C', 'percentage', 10);

        $this->expectException(InvalidArgumentException::class);

        app(CreateCouponAction::class)->execute($tenant->id, 'COUPON-AB12C', 'fixed_amount', 500);
    }

    public function test_execute_withExpiryAndMaxUses_persistsThem(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $result = app(CreateCouponAction::class)->execute(
            tenantId: $tenant->id,
            code: 'COUPON-AB12C',
            discountType: 'percentage',
            discountValue: 10,
            maxUses: 5,
            expiresAt: '2020-01-01T00:00:00+00:00',
        );

        $this->assertSame(5, $result->maxUses);
        $this->assertNotNull($result->expiresAt);
    }
}
