<?php

namespace Tests\Feature\Nexus\Approval;

use App\Domains\Nexus\Agent\Application\Actions\SetAuthorityLimitsAction;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Approval\Application\Actions\ApproveApprovalLevelAction;
use App\Domains\Nexus\Approval\Application\Actions\GetApprovalPolicyAction;
use App\Domains\Nexus\Approval\Application\Actions\RejectApprovalLevelAction;
use App\Domains\Nexus\Approval\Application\Actions\SetApprovalPolicyAction;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalRequestRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_twoLevelChain_endToEnd_managerThenCfo_reallyAcceptsAndGeneratesContract(): void
    {
        [$buyer, $buyerOwner, $manager, $cfo] = $this->buyerWithTeam();
        $seller = $this->verifiedBusiness('Seller Co');
        $this->lowerAuthorityLimit($buyer);
        $this->setPolicy($buyer, $buyerOwner->id);

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );
        $negotiation = app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        $this->assertSame('pending_approval', $negotiation->status);

        $afterManager = app(ApproveApprovalLevelAction::class)->execute($negotiation->id, $manager->id);
        $this->assertSame('pending_approval', $afterManager->status, 'still needs CFO approval');

        $afterCfo = app(ApproveApprovalLevelAction::class)->execute($negotiation->id, $cfo->id);
        $this->assertSame('accepted', $afterCfo->status);

        $this->assertDatabaseHas('contracts', ['negotiation_id' => $negotiation->id]);
    }

    public function test_wrongRole_cannotApproveCurrentLevel(): void
    {
        [$buyer, $buyerOwner, , $cfo] = $this->buyerWithTeam();
        $seller = $this->verifiedBusiness('Seller Co');
        $this->lowerAuthorityLimit($buyer);
        $this->setPolicy($buyer, $buyerOwner->id);
        $negotiation = $this->openPendingApproval($buyer, $seller);

        $this->expectException(InvalidArgumentException::class);

        // level 0 requires Manager, not Cfo
        app(ApproveApprovalLevelAction::class)->execute($negotiation->id, $cfo->id);
    }

    public function test_levelOneRejection_blocksLevelTwoFromEverBeingReachable(): void
    {
        [$buyer, $buyerOwner, $manager, $cfo] = $this->buyerWithTeam();
        $seller = $this->verifiedBusiness('Seller Co');
        $this->lowerAuthorityLimit($buyer);
        $this->setPolicy($buyer, $buyerOwner->id);
        $negotiation = $this->openPendingApproval($buyer, $seller);

        $result = app(RejectApprovalLevelAction::class)->execute($negotiation->id, $manager->id, 'too expensive');
        $this->assertSame('rejected', $result->status);

        $this->expectException(InvalidArgumentException::class);

        app(ApproveApprovalLevelAction::class)->execute($negotiation->id, $cfo->id);
    }

    public function test_zeroPolicyBusiness_regressesToOriginalSingleLevelBehavior(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $this->lowerAuthorityLimit($buyer);

        $negotiation = $this->openPendingApproval($buyer, $seller);

        $this->assertNull(app(ApprovalRequestRepositoryInterface::class)->findByNegotiationId($negotiation->id));
    }

    public function test_setApprovalPolicy_byNonOwner_throws(): void
    {
        [$buyer, , $manager] = $this->buyerWithTeam();

        $this->expectException(InvalidArgumentException::class);

        app(SetApprovalPolicyAction::class)->execute($buyer->id, $manager->id, [
            ['role' => 'manager', 'minAmount' => 0],
        ]);
    }

    public function test_getApprovalPolicy_beforeSet_returnsNull(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');

        $this->assertNull(app(GetApprovalPolicyAction::class)->execute($buyer->id));
    }

    private function openPendingApproval(BusinessData $buyer, BusinessData $seller)
    {
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );

        return app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
    }

    private function lowerAuthorityLimit(BusinessData $business): void
    {
        $agent = app(AgentRepositoryInterface::class)->findByBusinessId($business->id);
        app(SetAuthorityLimitsAction::class)->execute($agent->id(), ['max_deal_value' => 50000]);
    }

    private function setPolicy(BusinessData $business, int $ownerId): void
    {
        app(SetApprovalPolicyAction::class)->execute($business->id, $ownerId, [
            ['role' => 'manager', 'minAmount' => 0],
            ['role' => 'cfo', 'minAmount' => 80000],
        ]);
    }

    /**
     * @return array{0: BusinessData, 1: BusinessOwner, 2: BusinessOwner, 3: BusinessOwner}
     */
    private function buyerWithTeam(): array
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => 'owner@buyer.example.com',
            'password' => 'password123',
            'role' => TeamMemberRole::Owner->value,
        ]);
        $manager = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Manager Person',
            'email' => 'manager@buyer.example.com',
            'password' => 'password123',
            'role' => TeamMemberRole::Manager->value,
        ]);
        $cfo = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Cfo Person',
            'email' => 'cfo@buyer.example.com',
            'password' => 'password123',
            'role' => TeamMemberRole::Cfo->value,
        ]);

        return [$business, $owner, $manager, $cfo];
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
