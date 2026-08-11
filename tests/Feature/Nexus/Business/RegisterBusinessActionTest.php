<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasRegistered;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Exercises the full container + database wiring: RegisterBusinessAction
 * -> CreateTenantAction/CreateOrganizationAction -> EloquentBusinessRepository
 * -> the real tenants/organizations/businesses tables, via RefreshDatabase.
 * Nothing here is mocked — this is the test that proves Core's
 * previously-unchained primitives actually compose correctly.
 */
class RegisterBusinessActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_createsTenantOrganizationAndBusiness(): void
    {
        Event::fake();

        $result = app(RegisterBusinessAction::class)->execute(
            nameFa: 'شرکت آزمایشی',
            nameEn: 'Test Company',
            type: BusinessType::Company,
            industry: Industry::Technology,
        );

        $this->assertNotNull($result->id);
        $this->assertSame('شرکت آزمایشی', $result->nameFa);
        $this->assertSame('Test Company', $result->nameEn);
        $this->assertSame('company', $result->type);
        $this->assertSame('technology', $result->industry);
        $this->assertSame('pending', $result->verificationStatus);

        $this->assertDatabaseHas('tenants', ['id' => $result->tenantId, 'slug' => 'test-company', 'status' => 'pending']);
        $this->assertDatabaseHas('organizations', ['id' => $result->organizationId, 'tenant_id' => $result->tenantId]);
        $this->assertDatabaseHas('businesses', [
            'id' => $result->id,
            'tenant_id' => $result->tenantId,
            'organization_id' => $result->organizationId,
            'name_en' => 'Test Company',
            'verification_status' => 'pending',
        ]);
        $this->assertDatabaseCount('organization_members', 0);

        Event::assertDispatched(BusinessWasRegistered::class);
    }

    public function test_execute_withDuplicateBusinessName_generatesUniqueSlugInstead(): void
    {
        app(RegisterBusinessAction::class)->execute('شرکت اول', 'Test Company', BusinessType::Company, Industry::Technology);

        $second = app(RegisterBusinessAction::class)->execute('شرکت دوم', 'Test Company', BusinessType::Company, Industry::Retail);

        $this->assertDatabaseHas('tenants', ['id' => $second->tenantId, 'slug' => 'test-company-1']);
    }
}
