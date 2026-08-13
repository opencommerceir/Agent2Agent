<?php

namespace Tests\Unit\Nexus\Approval;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalRequest;
use App\Domains\Nexus\Approval\Domain\Exceptions\InvalidApprovalRequestStateException;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalRequestStatus;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use PHPUnit\Framework\TestCase;

class ApprovalRequestTest extends TestCase
{
    private function twoLevelRequest(): ApprovalRequest
    {
        return ApprovalRequest::open(1, 1, [
            new ApprovalLevel(TeamMemberRole::Manager, 0),
            new ApprovalLevel(TeamMemberRole::Cfo, 80000),
        ]);
    }

    public function test_open_startsAtLevelZeroPending(): void
    {
        $request = $this->twoLevelRequest();

        $this->assertSame(0, $request->currentLevelIndex());
        $this->assertSame(ApprovalRequestStatus::Pending, $request->status());
        $this->assertSame(TeamMemberRole::Manager, $request->currentRequiredRole());
    }

    public function test_approveCurrentLevel_advancesWithoutCompletingWhenMoreLevelsRemain(): void
    {
        $request = $this->twoLevelRequest();

        $request->approveCurrentLevel();

        $this->assertSame(1, $request->currentLevelIndex());
        $this->assertSame(ApprovalRequestStatus::Pending, $request->status());
        $this->assertSame(TeamMemberRole::Cfo, $request->currentRequiredRole());
    }

    public function test_approveCurrentLevel_onLastLevel_completes(): void
    {
        $request = $this->twoLevelRequest();
        $request->approveCurrentLevel();

        $request->approveCurrentLevel();

        $this->assertSame(ApprovalRequestStatus::Completed, $request->status());
    }

    public function test_reject_isTerminalRegardlessOfLevel(): void
    {
        $request = $this->twoLevelRequest();

        $request->reject();

        $this->assertSame(ApprovalRequestStatus::Rejected, $request->status());
    }

    public function test_approveCurrentLevel_afterCompleted_throws(): void
    {
        $request = $this->twoLevelRequest();
        $request->approveCurrentLevel();
        $request->approveCurrentLevel();

        $this->expectException(InvalidApprovalRequestStateException::class);

        $request->approveCurrentLevel();
    }

    public function test_approveCurrentLevel_afterRejected_throws(): void
    {
        $request = $this->twoLevelRequest();
        $request->reject();

        $this->expectException(InvalidApprovalRequestStateException::class);

        $request->approveCurrentLevel();
    }
}
