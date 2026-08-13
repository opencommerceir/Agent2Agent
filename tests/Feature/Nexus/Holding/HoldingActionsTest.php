<?php

namespace Tests\Feature\Nexus\Holding;

use App\Domains\Nexus\Analytics\Application\Actions\GetHoldingDashboardAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Holding\Application\Actions\AcceptSubsidiaryInvitationAction;
use App\Domains\Nexus\Holding\Application\Actions\CreateHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\GetHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\GetMyHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\InviteSubsidiaryAction;
use App\Domains\Nexus\Holding\Application\Actions\LeaveHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\ListHoldingInvitationsForBusinessAction;
use App\Domains\Nexus\Holding\Application\Actions\RejectSubsidiaryInvitationAction;
use App\Domains\Nexus\Holding\Application\Actions\RemoveSubsidiaryAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class HoldingActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_succeeds(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');

        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'هلدینگ الف', 'Alpha Holding');

        $this->assertSame('active', $holding->status);
        $this->assertSame($parent->id, $holding->parentBusinessId);
        $this->assertCount(0, $holding->subsidiaries);
    }

    public function test_create_twiceBySameParent_throws(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        app(CreateHoldingAction::class)->execute($parent->id, 'هلدینگ الف', 'Alpha Holding');

        $this->expectException(InvalidArgumentException::class);

        app(CreateHoldingAction::class)->execute($parent->id, 'هلدینگ ب', 'Beta Holding');
    }

    public function test_invite_thenAccept_makesSubsidiaryActive(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'هلدینگ الف', 'Alpha Holding');

        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);
        $invited = app(GetHoldingAction::class)->execute($holding->id);
        $this->assertSame('invited', $invited->subsidiaries[0]['status']);

        app(AcceptSubsidiaryInvitationAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);

        $accepted = app(GetHoldingAction::class)->execute($holding->id);
        $this->assertSame('active', $accepted->subsidiaries[0]['status']);
    }

    public function test_invite_byNonParent_throws(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $outsider = $this->verifiedBusiness('Outsider Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'هلدینگ الف', 'Alpha Holding');

        $this->expectException(InvalidArgumentException::class);

        app(InviteSubsidiaryAction::class)->execute($holding->id, $outsider->id, $sub->id);
    }

    public function test_invite_businessAlreadyInAnotherHolding_throws(): void
    {
        $parentA = $this->verifiedBusiness('Parent A');
        $parentB = $this->verifiedBusiness('Parent B');
        $sub = $this->verifiedBusiness('Sub Co');
        $holdingA = app(CreateHoldingAction::class)->execute($parentA->id, 'الف', 'A Holding');
        $holdingB = app(CreateHoldingAction::class)->execute($parentB->id, 'ب', 'B Holding');
        app(InviteSubsidiaryAction::class)->execute($holdingA->id, $parentA->id, $sub->id);

        $this->expectException(InvalidArgumentException::class);

        app(InviteSubsidiaryAction::class)->execute($holdingB->id, $parentB->id, $sub->id);
    }

    public function test_invite_anotherHoldingsParent_throws(): void
    {
        $parentA = $this->verifiedBusiness('Parent A');
        $parentB = $this->verifiedBusiness('Parent B');
        $holdingA = app(CreateHoldingAction::class)->execute($parentA->id, 'الف', 'A Holding');
        app(CreateHoldingAction::class)->execute($parentB->id, 'ب', 'B Holding');

        $this->expectException(InvalidArgumentException::class);

        app(InviteSubsidiaryAction::class)->execute($holdingA->id, $parentA->id, $parentB->id);
    }

    public function test_accept_byWrongBusiness_throws(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $outsider = $this->verifiedBusiness('Outsider Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);
        $invited = app(GetHoldingAction::class)->execute($holding->id);

        $this->expectException(InvalidArgumentException::class);

        app(AcceptSubsidiaryInvitationAction::class)->execute($invited->subsidiaries[0]['id'], $outsider->id);
    }

    public function test_reject_transitionsToRemoved(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);
        $invited = app(GetHoldingAction::class)->execute($holding->id);

        app(RejectSubsidiaryInvitationAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);

        $updated = app(GetHoldingAction::class)->execute($holding->id);
        $this->assertSame('removed', $updated->subsidiaries[0]['status']);

        // a Removed subsidiary no longer blocks a fresh invitation elsewhere
        $otherParent = $this->verifiedBusiness('Other Parent');
        $otherHolding = app(CreateHoldingAction::class)->execute($otherParent->id, 'ب', 'Beta');
        app(InviteSubsidiaryAction::class)->execute($otherHolding->id, $otherParent->id, $sub->id);
        $this->assertCount(1, app(GetHoldingAction::class)->execute($otherHolding->id)->subsidiaries);
    }

    public function test_remove_byParent_succeeds(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);
        $invited = app(GetHoldingAction::class)->execute($holding->id);
        app(AcceptSubsidiaryInvitationAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);

        app(RemoveSubsidiaryAction::class)->execute($invited->subsidiaries[0]['id'], $parent->id);

        $updated = app(GetHoldingAction::class)->execute($holding->id);
        $this->assertSame('removed', $updated->subsidiaries[0]['status']);
    }

    public function test_remove_byNonParent_throws(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);
        $invited = app(GetHoldingAction::class)->execute($holding->id);

        $this->expectException(InvalidArgumentException::class);

        app(RemoveSubsidiaryAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);
    }

    public function test_leave_bySubsidiaryItself_succeeds(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);
        $invited = app(GetHoldingAction::class)->execute($holding->id);
        app(AcceptSubsidiaryInvitationAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);

        app(LeaveHoldingAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);

        $this->assertNull(app(GetMyHoldingAction::class)->execute($sub->id));
    }

    public function test_getMyHolding_resolvesForParentAndActiveSubsidiaryOnly(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);

        $this->assertNotNull(app(GetMyHoldingAction::class)->execute($parent->id));
        $this->assertNull(app(GetMyHoldingAction::class)->execute($sub->id), 'still just Invited, not Active yet');

        $invited = app(GetHoldingAction::class)->execute($holding->id);
        app(AcceptSubsidiaryInvitationAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);

        $this->assertNotNull(app(GetMyHoldingAction::class)->execute($sub->id));
    }

    public function test_listInvitations_returnsOnlyPendingForThatBusiness(): void
    {
        $parent = $this->verifiedBusiness('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $outsider = $this->verifiedBusiness('Outsider Co');
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);

        $this->assertCount(1, app(ListHoldingInvitationsForBusinessAction::class)->execute($sub->id));
        $this->assertCount(0, app(ListHoldingInvitationsForBusinessAction::class)->execute($outsider->id));
    }

    public function test_dashboard_sumsCreditAcrossParentAndActiveSubsidiariesOnly(): void
    {
        $parent = $this->verifiedBusiness('Parent Co', 500);
        $sub = $this->verifiedBusiness('Sub Co', 300);
        $notYetJoined = $this->verifiedBusiness('Not Joined Co', 999);
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        app(InviteSubsidiaryAction::class)->execute($holding->id, $parent->id, $sub->id);
        $invited = app(GetHoldingAction::class)->execute($holding->id);
        app(AcceptSubsidiaryInvitationAction::class)->execute($invited->subsidiaries[0]['id'], $sub->id);

        $dashboard = app(GetHoldingDashboardAction::class)->execute($holding->id);

        $this->assertSame(800, $dashboard['totalCreditBalance']);
        $this->assertCount(2, $dashboard['rows']);
    }

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        if ($credits > 0) {
            app(GrantCreditsAction::class)->execute($business->id, $credits, CreditTransactionType::AdminGrant, 'test.seed');
        }

        return $business;
    }
}
