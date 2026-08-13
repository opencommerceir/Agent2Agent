<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\ChangeTeamMemberRoleAction;
use App\Domains\Nexus\Business\Application\Actions\CompleteForcedPasswordChangeAction;
use App\Domains\Nexus\Business\Application\Actions\InviteTeamMemberAction;
use App\Domains\Nexus\Business\Application\Actions\ListTeamMembersAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\RemoveTeamMemberAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class TeamMemberActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_byOwner_createsMemberWithTemporaryPasswordAndForcedChangeFlag(): void
    {
        [$business, $owner] = $this->businessWithOwner();

        $member = app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Manager Person', 'manager@example.com', TeamMemberRole::Manager);

        $this->assertSame('manager', $member->role);
        $this->assertTrue($member->mustChangePassword);
        $this->assertDatabaseHas('business_owners', ['email' => 'manager@example.com', 'role' => 'manager', 'must_change_password' => true]);
    }

    public function test_invite_byNonOwner_throws(): void
    {
        [$business, $owner] = $this->businessWithOwner();
        $staff = app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Staff Person', 'staff@example.com', TeamMemberRole::Staff);
        $staffModel = BusinessOwner::query()->find($staff->id);

        $this->expectException(InvalidArgumentException::class);

        app(InviteTeamMemberAction::class)->execute($business->id, $staffModel->id, 'Another Person', 'another@example.com', TeamMemberRole::Staff);
    }

    public function test_invite_duplicateEmail_throws(): void
    {
        [$business, $owner] = $this->businessWithOwner();
        app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Manager Person', 'manager@example.com', TeamMemberRole::Manager);

        $this->expectException(InvalidArgumentException::class);

        app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Someone Else', 'manager@example.com', TeamMemberRole::Staff);
    }

    public function test_listTeamMembers_scopedToBusiness(): void
    {
        [$businessA, $ownerA] = $this->businessWithOwner('A Co', 'a-owner@example.com');
        [$businessB] = $this->businessWithOwner('B Co', 'b-owner@example.com');
        app(InviteTeamMemberAction::class)->execute($businessA->id, $ownerA->id, 'Manager Person', 'manager@example.com', TeamMemberRole::Manager);

        $membersA = app(ListTeamMembersAction::class)->execute($businessA->id);
        $membersB = app(ListTeamMembersAction::class)->execute($businessB->id);

        $this->assertCount(2, $membersA);
        $this->assertCount(1, $membersB);
    }

    public function test_changeRole_byOwner_succeeds(): void
    {
        [$business, $owner] = $this->businessWithOwner();
        $member = app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Manager Person', 'manager@example.com', TeamMemberRole::Manager);

        $updated = app(ChangeTeamMemberRoleAction::class)->execute($business->id, $owner->id, $member->id, TeamMemberRole::Cfo);

        $this->assertSame('cfo', $updated->role);
    }

    public function test_changeRole_demotingLastOwner_throws(): void
    {
        [$business, $owner] = $this->businessWithOwner();

        $this->expectException(InvalidArgumentException::class);

        app(ChangeTeamMemberRoleAction::class)->execute($business->id, $owner->id, $owner->id, TeamMemberRole::Staff);
    }

    public function test_changeRole_demotingOwner_whenAnotherOwnerExists_succeeds(): void
    {
        [$business, $owner] = $this->businessWithOwner();
        $secondOwner = app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Second Owner', 'owner2@example.com', TeamMemberRole::Owner);

        $updated = app(ChangeTeamMemberRoleAction::class)->execute($business->id, $owner->id, $owner->id, TeamMemberRole::Staff);

        $this->assertSame('staff', $updated->role);
    }

    public function test_remove_lastOwner_throws(): void
    {
        [$business, $owner] = $this->businessWithOwner();

        $this->expectException(InvalidArgumentException::class);

        app(RemoveTeamMemberAction::class)->execute($business->id, $owner->id, $owner->id);
    }

    public function test_remove_nonOwnerMember_byOwner_succeeds(): void
    {
        [$business, $owner] = $this->businessWithOwner();
        $member = app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Staff Person', 'staff@example.com', TeamMemberRole::Staff);

        app(RemoveTeamMemberAction::class)->execute($business->id, $owner->id, $member->id);

        $this->assertDatabaseMissing('business_owners', ['id' => $member->id]);
    }

    public function test_completeForcedPasswordChange_clearsFlagAndUpdatesPassword(): void
    {
        [$business, $owner] = $this->businessWithOwner();
        $member = app(InviteTeamMemberAction::class)->execute($business->id, $owner->id, 'Manager Person', 'manager@example.com', TeamMemberRole::Manager);

        app(CompleteForcedPasswordChangeAction::class)->execute($member->id, 'a-real-new-password');

        $model = BusinessOwner::query()->find($member->id);
        $this->assertFalse($model->must_change_password);
        $this->assertTrue(Hash::check('a-real-new-password', $model->password));
    }

    /**
     * @return array{0: \App\Domains\Nexus\Business\Application\DTOs\BusinessData, 1: BusinessOwner}
     */
    private function businessWithOwner(string $nameEn = 'Test Company', string $email = 'owner@example.com'): array
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => $email,
            'password' => 'password123',
            'role' => TeamMemberRole::Owner->value,
        ]);

        return [$business, $owner];
    }
}
