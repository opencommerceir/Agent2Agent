<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\DeductCreditsAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DeductCreditsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withSufficientBalance_deductsAndLedgersIt(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 100, CreditTransactionType::AdminGrant, 'initial');

        $result = app(DeductCreditsAction::class)->execute($business->id, 20, 'nexus.negotiation.propose', relatedId: 7);

        $this->assertSame(80, $result->balance);
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $business->id,
            'type' => 'deduction',
            'amount' => 20,
            'reason' => 'nexus.negotiation.propose',
            'balance_after' => 80,
            'related_id' => 7,
        ]);
    }

    public function test_execute_withInsufficientBalance_throws(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 5, CreditTransactionType::AdminGrant, 'initial');

        $this->expectException(InsufficientCreditException::class);

        app(DeductCreditsAction::class)->execute($business->id, 20, 'nexus.negotiation.propose');
    }

    public function test_execute_withNoBalanceRow_throws(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $this->expectException(InvalidArgumentException::class);

        app(DeductCreditsAction::class)->execute($business->id, 1, 'nexus.negotiation.propose');
    }
}
