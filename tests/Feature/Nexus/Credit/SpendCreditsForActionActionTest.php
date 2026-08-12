<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendCreditsForActionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_forUnpricedAction_isFreeAndDoesNotLedger(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 10, CreditTransactionType::AdminGrant, 'test.seed');

        $result = app(SpendCreditsForActionAction::class)->execute($business->id, 'nexus.negotiation.status');

        $this->assertNull($result);
        $this->assertDatabaseHas('nexus_credit_balances', ['business_id' => $business->id, 'balance' => 10]);
        $this->assertDatabaseMissing('nexus_credit_transactions', ['business_id' => $business->id, 'reason' => 'nexus.negotiation.status']);
    }

    public function test_execute_forPricedAction_deductsAndLedgersIt(): void
    {
        config(['nexus.platform.credit.action_costs' => ['nexus.marketplace.search' => 5]]);
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 10, CreditTransactionType::AdminGrant, 'test.seed');

        $result = app(SpendCreditsForActionAction::class)->execute($business->id, 'nexus.marketplace.search');

        $this->assertSame(5, $result->balance);
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $business->id,
            'type' => 'deduction',
            'amount' => 5,
            'reason' => 'nexus.marketplace.search',
        ]);
    }

    public function test_execute_withInsufficientBalance_throws(): void
    {
        config(['nexus.platform.credit.action_costs' => ['nexus.marketplace.search' => 5]]);
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 2, CreditTransactionType::AdminGrant, 'test.seed');

        $this->expectException(InsufficientCreditException::class);

        app(SpendCreditsForActionAction::class)->execute($business->id, 'nexus.marketplace.search');
    }
}
