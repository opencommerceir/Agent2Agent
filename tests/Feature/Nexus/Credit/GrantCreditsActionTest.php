<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrantCreditsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_onFirstGrant_opensBalanceAndLedgersIt(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $result = app(GrantCreditsAction::class)->execute(
            businessId: $business->id,
            amount: 1000,
            type: CreditTransactionType::Purchase,
            reason: 'credit.purchase.starter',
        );

        $this->assertSame(1000, $result->balance);

        $this->assertDatabaseHas('nexus_credit_balances', [
            'business_id' => $business->id,
            'balance' => 1000,
        ]);
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $business->id,
            'type' => 'purchase',
            'amount' => 1000,
            'reason' => 'credit.purchase.starter',
            'balance_after' => 1000,
        ]);
    }

    public function test_execute_onExistingBalance_addsToIt(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 500, CreditTransactionType::AdminGrant, 'initial');

        $result = app(GrantCreditsAction::class)->execute($business->id, 200, CreditTransactionType::Refund, 'refund.negotiation');

        $this->assertSame(700, $result->balance);
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $business->id,
            'type' => 'refund',
            'amount' => 200,
            'balance_after' => 700,
        ]);
    }
}
