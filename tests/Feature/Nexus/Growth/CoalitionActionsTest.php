<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\CreateCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\JoinCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\LeaveCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\ListOpenCoalitionsAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CoalitionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_addsOrganizerAsFirstMemberAndDeductsCredit(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');

        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );

        $this->assertSame('forming', $coalition->status);
        $this->assertCount(1, $coalition->members);
        $this->assertSame($organizer->id, $coalition->members[0]['businessId']);
        $this->assertSame(5, $coalition->members[0]['quantity']);

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($organizer->id);
        $this->assertSame(90, $balance->balance());
    }

    public function test_create_withInsufficientCredit_throws(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 1);
        $target = $this->verifiedBusiness('Target Co');

        $this->expectException(InsufficientCreditException::class);

        app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );
    }

    public function test_join_addsMemberToOpenCoalition(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $joiner = $this->verifiedBusiness('Joiner Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );

        $updated = app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);

        $this->assertCount(2, $updated->members);
    }

    public function test_join_asTargetBusiness_throws(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );

        $this->expectException(InvalidArgumentException::class);

        app(JoinCoalitionAction::class)->execute($coalition->id, $target->id, 1);
    }

    public function test_join_twice_throws(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $joiner = $this->verifiedBusiness('Joiner Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);

        $this->expectException(InvalidArgumentException::class);

        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 1);
    }

    public function test_leave_removesMember(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $joiner = $this->verifiedBusiness('Joiner Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);

        app(LeaveCoalitionAction::class)->execute($coalition->id, $joiner->id);

        $updated = app(\App\Domains\Nexus\Growth\Application\Actions\GetCoalitionAction::class)->execute($coalition->id);
        $this->assertCount(1, $updated->members);
    }

    public function test_leave_asOrganizer_throws(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );

        $this->expectException(InvalidArgumentException::class);

        app(LeaveCoalitionAction::class)->execute($coalition->id, $organizer->id);
    }

    public function test_listOpenCoalitions_excludesTargetAndAlreadyJoined(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $joiner = $this->verifiedBusiness('Joiner Co');
        $outsider = $this->verifiedBusiness('Outsider Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);

        $this->assertCount(0, app(ListOpenCoalitionsAction::class)->execute($target->id));
        $this->assertCount(0, app(ListOpenCoalitionsAction::class)->execute($joiner->id));
        $this->assertCount(1, app(ListOpenCoalitionsAction::class)->execute($outsider->id));
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
