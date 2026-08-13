<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Analytics\Application\Actions\GetComplianceOverviewAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\SetDataResidencyRegionAction;
use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\DataResidencyRegion;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7/M10 — the Compliance Dashboard's read-model.
 */
class GetComplianceOverviewActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_onFreshInstall_reportsIntactChainAndZeroCounts(): void
    {
        $overview = app(GetComplianceOverviewAction::class)->execute();

        $this->assertTrue($overview['auditChain']['intact']);
        $this->assertSame(0, $overview['auditChain']['checkedCount']);
        $this->assertSame(0, $overview['totalBusinesses']);
        $this->assertSame(0, $overview['suspendedBusinessCount']);
        $this->assertSame(0, $overview['openDisputeCount']);
        $this->assertSame(0.0, $overview['mfaAdoptionRate']);
        // Fresh install: nothing to verify yet still counts as satisfied
        // (GetComplianceOverviewAction's own docblock) — there's no false
        // "BROKEN" banner just because no capability has been called yet.
        $checklistByKey = collect($overview['checklist'])->keyBy('key');
        $this->assertTrue($checklistByKey['immutable_audit_trail']['satisfied']);
    }

    public function test_execute_countsSuspendedBusinesses(): void
    {
        $business = $this->registerBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'fraud');

        $overview = app(GetComplianceOverviewAction::class)->execute();

        $this->assertSame(1, $overview['totalBusinesses']);
        $this->assertSame(1, $overview['suspendedBusinessCount']);
    }

    public function test_execute_dataResidencyBreakdown_separatesDeclaredFromUndeclared(): void
    {
        $declared = $this->registerBusiness('Declared Co');
        $this->registerBusiness('Undeclared Co');
        app(SetDataResidencyRegionAction::class)->execute($declared->id, DataResidencyRegion::EU);

        $overview = app(GetComplianceOverviewAction::class)->execute();

        $this->assertSame(1, $overview['dataResidencyBreakdown']['eu']);
        $this->assertSame(1, $overview['dataResidencyBreakdown']['undeclared']);

        $checklistByKey = collect($overview['checklist'])->keyBy('key');
        $this->assertTrue($checklistByKey['data_residency_declared']['satisfied']);
    }

    public function test_execute_withNoDeclaredRegions_checklistItemIsNotSatisfied(): void
    {
        $this->registerBusiness('Buyer Co');

        $overview = app(GetComplianceOverviewAction::class)->execute();

        $checklistByKey = collect($overview['checklist'])->keyBy('key');
        $this->assertFalse($checklistByKey['data_residency_declared']['satisfied']);
    }

    public function test_execute_mfaAdoptionRate_computedFromOwnersWithMfaEnabled(): void
    {
        $business = $this->registerBusiness('Buyer Co');
        BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner With MFA',
            'email' => 'mfa-'.uniqid().'@example.com',
            'password' => 'password123',
            'mfa_enabled_at' => now(),
        ]);
        BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Without MFA',
            'email' => 'no-mfa-'.uniqid().'@example.com',
            'password' => 'password123',
        ]);

        $overview = app(GetComplianceOverviewAction::class)->execute();

        $this->assertSame(50.0, $overview['mfaAdoptionRate']);
    }

    public function test_execute_ssoProviders_reflectsRegisteredProviders(): void
    {
        $overview = app(GetComplianceOverviewAction::class)->execute();

        $keys = array_column($overview['ssoProviders'], 'key');
        $this->assertContains('google', $keys);
        $this->assertContains('saml', $keys);
        $this->assertContains('ldap', $keys);
    }

    private function registerBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }
}
