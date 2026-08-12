<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real, non-faked event flow: VerifyBusinessAction dispatches
 * BusinessWasVerified -> GrantStartingCreditsOnBusinessVerifiedListener
 * (registered in NexusServiceProvider::boot(), alongside Agent's own
 * listener on the same event) — no direct call between the two domains,
 * only an event in between.
 */
class GrantStartingCreditsOnBusinessVerifiedListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifyingABusiness_withConfiguredStartingBalance_opensAndCreditsIt(): void
    {
        config(['nexus.platform.credit.starting_balance' => 500]);

        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($business->id);

        $this->assertNotNull($balance);
        $this->assertSame(500, $balance->balance());
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $business->id,
            'type' => 'admin_grant',
            'amount' => 500,
            'reason' => 'business.verified.starting_balance',
        ]);
    }

    public function test_verifyingABusiness_withZeroStartingBalance_stillOpensBalanceRow(): void
    {
        config(['nexus.platform.credit.starting_balance' => 0]);

        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($business->id);

        $this->assertNotNull($balance);
        $this->assertSame(0, $balance->balance());
        $this->assertDatabaseMissing('nexus_credit_transactions', [
            'business_id' => $business->id,
        ]);
    }
}
