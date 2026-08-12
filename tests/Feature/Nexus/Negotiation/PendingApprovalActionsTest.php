<?php

namespace Tests\Feature\Nexus\Negotiation;

use App\Domains\Nexus\Agent\Application\Actions\SetAuthorityLimitsAction;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\ApprovePendingNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectPendingNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PendingApprovalActionsTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        // Phase 3/M2's CostGate now gates propose/accept (and, via the
        // contract-generation listener, the initiator too) — a generous
        // flat top-up so this domain's own tests keep exercising the
        // pending-approval flow, not credit exhaustion.
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    private function pendingNegotiation(): array
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $buyerAgent = app(AgentRepositoryInterface::class)->findByBusinessId($buyer->id);
        app(SetAuthorityLimitsAction::class)->execute($buyerAgent->id(), ['max_deal_value' => 50000]);

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );
        $negotiation = app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        return [$buyer, $seller, $negotiation];
    }

    public function test_approvePendingNegotiation_movesToAcceptedAndDispatchesEvent(): void
    {
        Event::fake([NegotiationWasAccepted::class]);
        [$buyer, , $negotiation] = $this->pendingNegotiation();
        $this->assertSame('pending_approval', $negotiation->status);

        $result = app(ApprovePendingNegotiationAction::class)->execute($negotiation->id, $buyer->id);

        $this->assertSame('accepted', $result->status);
        Event::assertDispatched(NegotiationWasAccepted::class);
    }

    public function test_rejectPendingNegotiation_movesToRejected(): void
    {
        [$buyer, , $negotiation] = $this->pendingNegotiation();

        $result = app(RejectPendingNegotiationAction::class)->execute($negotiation->id, $buyer->id, 'over budget');

        $this->assertSame('rejected', $result->status);
        $this->assertSame('over budget', $result->rejectionReason);
    }

    public function test_approvePendingNegotiation_byNonParty_throwsInvalidArgumentException(): void
    {
        [, , $negotiation] = $this->pendingNegotiation();
        $outsider = $this->verifiedBusiness('Outsider Co');

        $this->expectException(\InvalidArgumentException::class);

        app(ApprovePendingNegotiationAction::class)->execute($negotiation->id, $outsider->id);
    }
}
