<?php

namespace Tests\Unit\Core;

use App\Core\Domain\Entities\Tenant;
use App\Core\Domain\ValueObjects\TenantStatus;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database. Tenant is
 * framework-free by design (Domain Layer Rules), so it can be exercised
 * directly with plain PHPUnit.
 */
class TenantTest extends TestCase
{
    public function test_register_withValidData_createsTenantWithPendingStatus(): void
    {
        $tenant = Tenant::register('Acme Inc', 'acme-inc');

        $this->assertNull($tenant->id());
        $this->assertSame('Acme Inc', $tenant->name());
        $this->assertSame('acme-inc', $tenant->slug());
        $this->assertSame(TenantStatus::Pending, $tenant->status());
        $this->assertFalse($tenant->isActive());
    }

    public function test_activate_onPendingTenant_setsStatusToActive(): void
    {
        $tenant = Tenant::register('Acme Inc', 'acme-inc');

        $tenant->activate();

        $this->assertSame(TenantStatus::Active, $tenant->status());
        $this->assertTrue($tenant->isActive());
    }

    public function test_suspend_onActiveTenant_setsStatusToSuspended(): void
    {
        $tenant = Tenant::register('Acme Inc', 'acme-inc');
        $tenant->activate();

        $tenant->suspend();

        $this->assertSame(TenantStatus::Suspended, $tenant->status());
        $this->assertFalse($tenant->isActive());
    }

    public function test_isActive_forNonActiveStatuses_returnsFalse(): void
    {
        $tenant = Tenant::register('Acme Inc', 'acme-inc');

        $this->assertFalse($tenant->isActive());

        $tenant->activate();
        $tenant->suspend();

        $this->assertFalse($tenant->isActive());
    }
}
