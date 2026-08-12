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
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\SendCounterOfferAction;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;
use App\Domains\Nexus\Negotiation\Domain\Exceptions\NegotiationRoundLimitExceededException;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Exercises the whole Negotiation domain against real cross-tenant data
 * (two genuinely different Businesses/Tenants, not mocked) — the concrete
 * proof the "both sides stored explicitly, authorized by party membership
 * rather than a single tenant scope" design (Negotiation entity's own
 * docblock) actually works end to end.
 */
class NegotiationActionsTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        // Phase 3/M2's CostGate now gates propose/counter/accept/reject —
        // a generous flat top-up so this domain's own tests keep exercising
        // negotiation mechanics, not credit exhaustion.
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    private function terms(int $amount = 100000): NegotiationTerms
    {
        return new NegotiationTerms(Money::fromAmount($amount, 'IRT'), 1, null);
    }

    public function test_initiateNegotiation_createsProposalMessage(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');

        $result = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms(),
        );

        $this->assertSame('proposed', $result->status);
        $this->assertDatabaseHas('negotiations', [
            'id' => $result->id,
            'initiator_business_id' => $buyer->id,
            'initiator_tenant_id' => $buyer->tenantId,
            'counterparty_business_id' => $seller->id,
            'counterparty_tenant_id' => $seller->tenantId,
        ]);
        $this->assertDatabaseHas('negotiation_messages', ['negotiation_id' => $result->id, 'type' => 'proposal']);
    }

    public function test_initiateNegotiation_withSameBusinessOnBothSides_throwsInvalidArgumentException(): void
    {
        $business = $this->verifiedBusiness('Solo Co');

        $this->expectException(InvalidArgumentException::class);

        app(InitiateNegotiationAction::class)->execute($business->id, $business->id, CatalogItemType::Product, 1, $this->terms());
    }

    public function test_sendCounterOffer_updatesTermsAndCounterparty(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms(100000));

        $result = app(SendCounterOfferAction::class)->execute($negotiation->id, $seller->id, $this->terms(90000));

        $this->assertSame('countered', $result->status);
        $this->assertSame(90000, $result->currentTerms['priceAmount']);
        $this->assertDatabaseHas('negotiation_messages', ['negotiation_id' => $negotiation->id, 'type' => 'counter', 'sender_business_id' => $seller->id]);
    }

    public function test_sendCounterOffer_byNonParty_throwsInvalidArgumentException(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $outsider = $this->verifiedBusiness('Outsider Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        $this->expectException(InvalidArgumentException::class);

        app(SendCounterOfferAction::class)->execute($negotiation->id, $outsider->id, $this->terms(90000));
    }

    public function test_sendCounterOffer_beyondMaxRounds_throwsRoundLimitException(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms(), maxRounds: 2);
        app(SendCounterOfferAction::class)->execute($negotiation->id, $seller->id, $this->terms(90000));

        $this->expectException(NegotiationRoundLimitExceededException::class);

        app(SendCounterOfferAction::class)->execute($negotiation->id, $buyer->id, $this->terms(95000));
    }

    public function test_acceptDeal_withinAuthorityLimit_acceptsDirectlyAndDispatchesEvent(): void
    {
        Event::fake([NegotiationWasAccepted::class]);

        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $buyerAgent = app(AgentRepositoryInterface::class)->findByBusinessId($buyer->id);
        app(SetAuthorityLimitsAction::class)->execute($buyerAgent->id(), ['max_deal_value' => 500000]);

        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms(100000));

        $result = app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $this->assertSame('accepted', $result->status);
        Event::assertDispatched(NegotiationWasAccepted::class, fn ($event) => $event->negotiation->id() === $negotiation->id);
    }

    public function test_acceptDeal_beyondAuthorityLimit_requestsApprovalInsteadOfAccepting(): void
    {
        Event::fake([NegotiationWasAccepted::class]);

        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $buyerAgent = app(AgentRepositoryInterface::class)->findByBusinessId($buyer->id);
        app(SetAuthorityLimitsAction::class)->execute($buyerAgent->id(), ['max_deal_value' => 50000]);

        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms(100000));

        $result = app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $this->assertSame('pending_approval', $result->status);
        Event::assertNotDispatched(NegotiationWasAccepted::class);
    }

    public function test_acceptDeal_withNoAuthorityLimitsConfigured_acceptsDirectly(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms(100000));

        $result = app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $this->assertSame('accepted', $result->status);
    }

    public function test_rejectDeal_setsRejectedStatusAndReason(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        $result = app(RejectDealAction::class)->execute($negotiation->id, $seller->id, 'price too low');

        $this->assertSame('rejected', $result->status);
        $this->assertSame('price too low', $result->rejectionReason);
    }
}
