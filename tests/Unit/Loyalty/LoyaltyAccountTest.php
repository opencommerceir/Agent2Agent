<?php

namespace Tests\Unit\Loyalty;

use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount;
use App\Modules\Loyalty\Domain\Exceptions\InsufficientPointsException;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use PHPUnit\Framework\TestCase;

class LoyaltyAccountTest extends TestCase
{
    public function test_open_startsAtZeroBalance(): void
    {
        $account = LoyaltyAccount::open(1, 1);

        $this->assertSame(0, $account->totalPointsEarned()->value());
        $this->assertSame(0, $account->totalPointsRedeemed()->value());
        $this->assertSame(0, $account->currentBalance()->value());
    }

    public function test_earn_increasesTotalEarnedAndCurrentBalance(): void
    {
        $account = LoyaltyAccount::open(1, 1);

        $account->earn(new Points(150));

        $this->assertSame(150, $account->totalPointsEarned()->value());
        $this->assertSame(150, $account->currentBalance()->value());
        $this->assertSame(0, $account->totalPointsRedeemed()->value());
    }

    public function test_redeem_withSufficientBalance_decreasesBalanceAndIncreasesRedeemedTotal(): void
    {
        $account = LoyaltyAccount::open(1, 1);
        $account->earn(new Points(150));

        $account->redeem(new Points(100));

        $this->assertSame(50, $account->currentBalance()->value());
        $this->assertSame(100, $account->totalPointsRedeemed()->value());
        $this->assertSame(150, $account->totalPointsEarned()->value()); // earned total untouched by redeem
    }

    public function test_redeem_withInsufficientBalance_throwsInsufficientPointsException(): void
    {
        $account = LoyaltyAccount::open(1, 1);
        $account->earn(new Points(50));

        $this->expectException(InsufficientPointsException::class);

        $account->redeem(new Points(200));
    }

    public function test_expire_decreasesCurrentBalanceOnly_notRedeemedTotal(): void
    {
        $account = LoyaltyAccount::open(1, 1);
        $account->earn(new Points(150));

        $account->expire(new Points(50));

        $this->assertSame(100, $account->currentBalance()->value());
        $this->assertSame(0, $account->totalPointsRedeemed()->value());
        $this->assertSame(150, $account->totalPointsEarned()->value());
    }

    public function test_expire_clampsToWhateverBalanceRemains(): void
    {
        $account = LoyaltyAccount::open(1, 1);
        $account->earn(new Points(100));
        $account->redeem(new Points(90));

        // Only 10 remain — expiring 100 must not go negative.
        $account->expire(new Points(100));

        $this->assertSame(0, $account->currentBalance()->value());
    }

    public function test_adjust_withPositiveDelta_increasesBalance(): void
    {
        $account = LoyaltyAccount::open(1, 1);

        $account->adjust(25);

        $this->assertSame(25, $account->currentBalance()->value());
    }

    public function test_adjust_withNegativeDelta_decreasesBalanceClampedAtZero(): void
    {
        $account = LoyaltyAccount::open(1, 1);
        $account->earn(new Points(10));

        $account->adjust(-30);

        $this->assertSame(0, $account->currentBalance()->value());
    }
}
