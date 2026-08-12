<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\DeductCreditsAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Application\Actions\RefundCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundCreditsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_creditsBackAndLedgersAsRefund(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 100, CreditTransactionType::AdminGrant, 'initial');
        app(DeductCreditsAction::class)->execute($business->id, 20, 'nexus.negotiation.propose', relatedId: 7);

        $result = app(RefundCreditsAction::class)->execute($business->id, 20, 'negotiation.rejected.refund', relatedId: 7);

        $this->assertSame(100, $result->balance);
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $business->id,
            'type' => 'refund',
            'amount' => 20,
            'reason' => 'negotiation.rejected.refund',
            'balance_after' => 100,
            'related_id' => 7,
        ]);
    }
}
