<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Growth\Application\Actions\RecordReferralSignupAction;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralSignupRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full referral lifecycle: a verified Business's own code (auto-issued by
 * IssueReferralCodeOnBusinessVerifiedListener) -> a new registrant records a
 * Pending signup against it -> once that new Business is itself Verified,
 * both sides are credited exactly once (GrantReferralRewardOnBusinessVerifiedListener)
 * -> a second-generation referral pays a smaller tier-2 bonus to the
 * original referrer.
 */
class ReferralSignupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifiedBusiness_getsAReferralCodeAutomatically(): void
    {
        $business = $this->registerBusiness('Root Co');
        $this->verify($business->id);

        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($business->id);

        $this->assertNotNull($code);
        $this->assertStringStartsWith('REF-', $code->code());
    }

    public function test_registeringWithValidCode_recordsPendingSignup(): void
    {
        $referrer = $this->registerBusiness('Referrer Co');
        $this->verify($referrer->id);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($referrer->id);

        $referee = $this->registerBusiness('Referee Co');
        app(RecordReferralSignupAction::class)->execute($referee->id, $code->code());

        $signup = app(ReferralSignupRepositoryInterface::class)->findByRefereeId($referee->id);
        $this->assertNotNull($signup);
        $this->assertTrue($signup->isPending());
        $this->assertSame($referrer->id, $signup->referrerBusinessId());
    }

    public function test_registeringWithUnknownCode_isSilentNoOp(): void
    {
        $referee = $this->registerBusiness('Referee Co');

        $signup = app(RecordReferralSignupAction::class)->execute($referee->id, 'REF-NOTREAL');

        $this->assertNull($signup);
        $this->assertNull(app(ReferralSignupRepositoryInterface::class)->findByRefereeId($referee->id));
    }

    public function test_verifyingReferee_rewardsBothSidesExactlyOnce(): void
    {
        $referrer = $this->registerBusiness('Referrer Co');
        $this->verify($referrer->id);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($referrer->id);

        $referee = $this->registerBusiness('Referee Co');
        app(RecordReferralSignupAction::class)->execute($referee->id, $code->code());

        $this->verify($referee->id);

        $balances = app(CreditBalanceRepositoryInterface::class);
        $this->assertSame(200, $balances->findByBusinessId($referrer->id)->balance());
        $this->assertSame(100, $balances->findByBusinessId($referee->id)->balance());

        $signup = app(ReferralSignupRepositoryInterface::class)->findByRefereeId($referee->id);
        $this->assertFalse($signup->isPending());
    }

    public function test_rewardListener_calledTwiceForAnAlreadyRewardedSignup_doesNotDoublePay(): void
    {
        $referrer = $this->registerBusiness('Referrer Co');
        $this->verify($referrer->id);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($referrer->id);

        $referee = $this->registerBusiness('Referee Co');
        app(RecordReferralSignupAction::class)->execute($referee->id, $code->code());
        $this->verify($referee->id);

        $event = new \App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified(
            app(\App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface::class)->findById($referee->id)
        );
        app(\App\Domains\Nexus\Growth\Application\Listeners\GrantReferralRewardOnBusinessVerifiedListener::class)->handle($event);

        $balances = app(CreditBalanceRepositoryInterface::class);
        $this->assertSame(200, $balances->findByBusinessId($referrer->id)->balance());
        $this->assertSame(100, $balances->findByBusinessId($referee->id)->balance());
    }

    public function test_secondGenerationReferral_paysTier2BonusToOriginalReferrer(): void
    {
        $grandparent = $this->registerBusiness('Grandparent Co');
        $this->verify($grandparent->id);
        $grandparentCode = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($grandparent->id);

        $parent = $this->registerBusiness('Parent Co');
        app(RecordReferralSignupAction::class)->execute($parent->id, $grandparentCode->code());
        $this->verify($parent->id);
        $parentCode = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($parent->id);

        $child = $this->registerBusiness('Child Co');
        app(RecordReferralSignupAction::class)->execute($child->id, $parentCode->code());
        $this->verify($child->id);

        $balances = app(CreditBalanceRepositoryInterface::class);
        // Grandparent: 200 (as parent's referrer) + 50 (tier-2 on child's verification).
        $this->assertSame(250, $balances->findByBusinessId($grandparent->id)->balance());
        // Parent: 100 (as child's referee reward, from being referred) + 200 (as child's referrer).
        $this->assertSame(300, $balances->findByBusinessId($parent->id)->balance());
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
