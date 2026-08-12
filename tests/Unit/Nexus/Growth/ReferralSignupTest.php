<?php

namespace Tests\Unit\Nexus\Growth;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralSignup;
use App\Domains\Nexus\Growth\Domain\ValueObjects\ReferralSignupStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReferralSignupTest extends TestCase
{
    public function test_record_startsPending(): void
    {
        $signup = ReferralSignup::record(1, 2, 'REF-ABC123');

        $this->assertSame(ReferralSignupStatus::Pending, $signup->status());
        $this->assertTrue($signup->isPending());
        $this->assertNull($signup->rewardedAt());
    }

    public function test_record_selfReferral_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReferralSignup::record(1, 1, 'REF-ABC123');
    }

    public function test_reward_transitionsToRewardedOnce(): void
    {
        $signup = ReferralSignup::record(1, 2, 'REF-ABC123');

        $signup->reward();

        $this->assertSame(ReferralSignupStatus::Rewarded, $signup->status());
        $this->assertFalse($signup->isPending());
        $this->assertNotNull($signup->rewardedAt());
    }

    public function test_reward_isIdempotent(): void
    {
        $signup = ReferralSignup::record(1, 2, 'REF-ABC123');
        $signup->reward();
        $firstRewardedAt = $signup->rewardedAt();

        $signup->reward();

        $this->assertSame($firstRewardedAt, $signup->rewardedAt());
    }
}
