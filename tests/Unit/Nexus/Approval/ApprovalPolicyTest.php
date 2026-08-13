<?php

namespace Tests\Unit\Nexus\Approval;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalPolicy;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ApprovalPolicyTest extends TestCase
{
    public function test_define_withEmptyLevels_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApprovalPolicy::define(1, []);
    }

    public function test_define_withDecreasingAmounts_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApprovalPolicy::define(1, [
            new ApprovalLevel(TeamMemberRole::Cfo, 100000),
            new ApprovalLevel(TeamMemberRole::Manager, 50000),
        ]);
    }

    public function test_levelsRequiredFor_returnsLevelsMetByAmount(): void
    {
        $policy = ApprovalPolicy::define(1, [
            new ApprovalLevel(TeamMemberRole::Manager, 0),
            new ApprovalLevel(TeamMemberRole::Cfo, 80000),
        ]);

        $required = $policy->levelsRequiredFor(100000);

        $this->assertCount(2, $required);
        $this->assertSame(TeamMemberRole::Manager, $required[0]->role);
        $this->assertSame(TeamMemberRole::Cfo, $required[1]->role);
    }

    public function test_levelsRequiredFor_belowAllThresholds_returnsLowestLevelOnly(): void
    {
        $policy = ApprovalPolicy::define(1, [
            new ApprovalLevel(TeamMemberRole::Manager, 50000),
            new ApprovalLevel(TeamMemberRole::Cfo, 80000),
        ]);

        $required = $policy->levelsRequiredFor(1000);

        $this->assertCount(1, $required);
        $this->assertSame(TeamMemberRole::Manager, $required[0]->role);
    }

    public function test_redefine_replacesLevels(): void
    {
        $policy = ApprovalPolicy::define(1, [new ApprovalLevel(TeamMemberRole::Manager, 0)]);

        $policy->redefine([new ApprovalLevel(TeamMemberRole::Cfo, 0)]);

        $this->assertSame(TeamMemberRole::Cfo, $policy->levels()[0]->role);
    }
}
