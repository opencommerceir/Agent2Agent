<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Analytics\Application\Actions\GetGrowthDashboardAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\RecordReferralSignupAction;
use App\Domains\Nexus\Growth\Application\Actions\SendAgentInviteAction;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetGrowthDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withNoInvites_returnsZeroKFactor(): void
    {
        $dashboard = app(GetGrowthDashboardAction::class)->execute();

        $this->assertSame(0.0, $dashboard->kFactor);
        $this->assertSame(0, $dashboard->invitesSent);
        $this->assertSame(0.0, $dashboard->conversionRatePercent);
    }

    public function test_execute_computesKFactorFromSentAndConverted(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 100);
        // 2 invites sent, 1 converted -> conversionRate 0.5, avgPerBusiness 2 (1 inviting business) -> kFactor 1.0
        app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead One', 'one@example.com');
        app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Two', 'two@example.com');

        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($inviter->id)->code();
        $referee = $this->registerBusiness('Referee Co');
        app(RecordReferralSignupAction::class)->execute($referee->id, $code);

        $dashboard = app(GetGrowthDashboardAction::class)->execute();

        $this->assertSame(2, $dashboard->invitesSent);
        $this->assertSame(1, $dashboard->invitesConverted);
        $this->assertSame(50.0, $dashboard->conversionRatePercent);
        $this->assertSame(1, $dashboard->invitingBusinesses);
        $this->assertSame(1.0, $dashboard->kFactor); // 2 avg per business * 0.5 conversion
    }

    public function test_execute_groupsVariantsCorrectly(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 100);
        app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead A', 'a@example.com', 'a');
        app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead B', 'b@example.com', 'b');

        $dashboard = app(GetGrowthDashboardAction::class)->execute();

        $variants = array_column($dashboard->variants, 'sent', 'variant');
        $this->assertSame(1, $variants['a']);
        $this->assertSame(1, $variants['b']);
    }

    public function test_execute_groupsBusinessesIntoRegistrationCohorts(): void
    {
        $this->registerBusiness('Cohort Co');

        $dashboard = app(GetGrowthDashboardAction::class)->execute();

        $this->assertCount(1, $dashboard->cohorts);
        $this->assertSame(1, $dashboard->cohorts[0]['businessesRegistered']);
    }

    public function test_execute_marksReferredBusinessInCohort(): void
    {
        $referrer = $this->verifiedBusiness('Referrer Co', 100);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($referrer->id)->code();
        $referee = $this->registerBusiness('Referee Co');
        app(RecordReferralSignupAction::class)->execute($referee->id, $code);

        $dashboard = app(GetGrowthDashboardAction::class)->execute();

        $totalReferred = array_sum(array_column($dashboard->cohorts, 'referredCount'));
        $this->assertSame(1, $totalReferred);
    }

    private function registerBusiness(string $nameEn): BusinessData
    {
        return app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
    }

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = $this->registerBusiness($nameEn);
        app(VerifyBusinessAction::class)->execute($business->id);

        if ($credits > 0) {
            app(GrantCreditsAction::class)->execute($business->id, $credits, CreditTransactionType::AdminGrant, 'test.seed');
        }

        return $business;
    }
}
