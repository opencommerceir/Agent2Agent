<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GetCreditBalanceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returnsCurrentBalance(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(GrantCreditsAction::class)->execute($business->id, 250, CreditTransactionType::AdminGrant, 'initial');

        $result = app(GetCreditBalanceAction::class)->execute($business->id);

        $this->assertSame($business->id, $result->businessId);
        $this->assertSame(250, $result->balance);
    }

    public function test_execute_withNoBalanceRow_throws(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $this->expectException(InvalidArgumentException::class);

        app(GetCreditBalanceAction::class)->execute($business->id);
    }
}
