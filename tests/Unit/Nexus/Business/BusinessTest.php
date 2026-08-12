<?php

namespace Tests\Unit\Nexus\Business;

use App\Domains\Nexus\Business\Domain\Entities\Business;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessStatus;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\VerificationStatus;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database. Business
 * is framework-free by design (Domain Layer Rules).
 */
class BusinessTest extends TestCase
{
    public function test_register_withValidData_createsBusinessWithPendingStatus(): void
    {
        $business = Business::register(
            tenantId: 1,
            organizationId: 1,
            nameFa: 'شرکت آزمایشی',
            nameEn: 'Test Company',
            type: BusinessType::Company,
            industry: Industry::Technology,
        );

        $this->assertNull($business->id());
        $this->assertSame(1, $business->tenantId());
        $this->assertSame(1, $business->organizationId());
        $this->assertSame('شرکت آزمایشی', $business->nameFa());
        $this->assertSame('Test Company', $business->nameEn());
        $this->assertSame(BusinessType::Company, $business->type());
        $this->assertSame(Industry::Technology, $business->industry());
        $this->assertSame(VerificationStatus::Pending, $business->verificationStatus());
        $this->assertFalse($business->isVerified());
        $this->assertNull($business->logoPath());
        $this->assertNull($business->documents());
        $this->assertSame(BusinessStatus::Active, $business->status());
        $this->assertTrue($business->isActive());
    }

    public function test_suspend_thenReactivate_toggleActiveIndependentlyOfVerification(): void
    {
        $business = Business::register(1, 1, 'شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        $business->verify();

        $business->suspend();

        $this->assertSame(BusinessStatus::Suspended, $business->status());
        $this->assertFalse($business->isActive());
        // Suspension is independent of verification — a suspended
        // Business's identity is still verified.
        $this->assertTrue($business->isVerified());

        $business->reactivate();

        $this->assertTrue($business->isActive());
    }

    public function test_verify_onPendingBusiness_setsStatusToVerified(): void
    {
        $business = Business::register(1, 1, 'شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $business->verify();

        $this->assertSame(VerificationStatus::Verified, $business->verificationStatus());
        $this->assertTrue($business->isVerified());
    }

    public function test_updateProfile_changesNamesTypeAndIndustry(): void
    {
        $business = Business::register(1, 1, 'شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $business->updateProfile('شرکت جدید', 'New Company', BusinessType::Individual, Industry::Retail);

        $this->assertSame('شرکت جدید', $business->nameFa());
        $this->assertSame('New Company', $business->nameEn());
        $this->assertSame(BusinessType::Individual, $business->type());
        $this->assertSame(Industry::Retail, $business->industry());
    }

    public function test_attachLogo_setsLogoPath(): void
    {
        $business = Business::register(1, 1, 'شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $business->attachLogo('logos/test.png');

        $this->assertSame('logos/test.png', $business->logoPath());
    }

    public function test_attachDocuments_setsDocuments(): void
    {
        $business = Business::register(1, 1, 'شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $business->attachDocuments(['docs/license.pdf']);

        $this->assertSame(['docs/license.pdf'], $business->documents());
    }
}
