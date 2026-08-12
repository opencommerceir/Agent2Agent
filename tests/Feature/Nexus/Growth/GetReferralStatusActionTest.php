<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Growth\Application\Actions\GetReferralStatusAction;
use App\Domains\Nexus\Growth\Application\Actions\RecordReferralSignupAction;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetReferralStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withNoReferrals_returnsZeroCounts(): void
    {
        $business = $this->registerBusiness('Solo Co');
        $this->verify($business->id);

        $status = app(GetReferralStatusAction::class)->execute($business->id);

        $this->assertNotNull($status->code);
        $this->assertSame(0, $status->tier1Count);
        $this->assertSame(0, $status->tier1RewardedCount);
        $this->assertSame(0, $status->tier2Count);
        $this->assertSame([], $status->referrals);
    }

    public function test_execute_withPendingAndRewardedReferrals_countsCorrectly(): void
    {
        $referrer = $this->registerBusiness('Referrer Co');
        $this->verify($referrer->id);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($referrer->id);

        $rewardedReferee = $this->registerBusiness('Rewarded Referee');
        app(RecordReferralSignupAction::class)->execute($rewardedReferee->id, $code->code());
        $this->verify($rewardedReferee->id);

        $pendingReferee = $this->registerBusiness('Pending Referee');
        app(RecordReferralSignupAction::class)->execute($pendingReferee->id, $code->code());

        $status = app(GetReferralStatusAction::class)->execute($referrer->id);

        $this->assertSame(2, $status->tier1Count);
        $this->assertSame(1, $status->tier1RewardedCount);
        $this->assertCount(2, $status->referrals);
    }

    private function registerBusiness(string $nameEn): BusinessData
    {
        return app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
    }

    private function verify(int $businessId): void
    {
        app(VerifyBusinessAction::class)->execute($businessId);
    }
}
