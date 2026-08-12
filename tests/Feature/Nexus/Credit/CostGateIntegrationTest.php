<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Agent\Application\Actions\SetAuthorityLimitsAction;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\SendCounterOfferAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the CostGate (SpendCreditsForActionAction, Phase 3/M2) actually
 * gates each retrofitted Action — real config prices, real
 * InsufficientCreditException on a too-low balance, a real ledger row on
 * success. NegotiationActionsTest/SearchMarketplaceActionTest etc. already
 * cover each Action's own domain behavior with a generous credit top-up;
 * this file is the credit-specific counterpart.
 */
class CostGateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        if ($credits > 0) {
            app(GrantCreditsAction::class)->execute($business->id, $credits, CreditTransactionType::AdminGrant, 'test.seed');
        }

        return $business;
    }

    private function terms(int $amount = 100000): NegotiationTerms
    {
        return new NegotiationTerms(Money::fromAmount($amount, 'IRT'), 1, null);
    }

    public function test_searchMarketplace_withInsufficientCredit_throws(): void
    {
        $caller = $this->verifiedBusiness('Caller Co', credits: 1);

        $this->expectException(InsufficientCreditException::class);

        app(SearchMarketplaceAction::class)->execute($caller->id);
    }

    public function test_searchMarketplace_withSufficientCredit_deductsAndLedgers(): void
    {
        $caller = $this->verifiedBusiness('Caller Co', credits: 100);

        app(SearchMarketplaceAction::class)->execute($caller->id);

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($caller->id);
        $this->assertSame(95, $balance->balance());
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $caller->id,
            'reason' => 'nexus.marketplace.search',
            'amount' => 5,
        ]);
    }

    public function test_initiateNegotiation_withInsufficientCredit_throws(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 1);
        $seller = $this->verifiedBusiness('Seller Co');

        $this->expectException(InsufficientCreditException::class);

        app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());
    }

    public function test_initiateNegotiation_withSufficientCredit_deductsAndLedgers(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 100);
        $seller = $this->verifiedBusiness('Seller Co');

        app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($buyer->id);
        $this->assertSame(80, $balance->balance());
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $buyer->id,
            'reason' => 'nexus.negotiation.propose',
            'amount' => 20,
        ]);
    }

    public function test_sendCounterOffer_withInsufficientCredit_throws(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 100);
        $seller = $this->verifiedBusiness('Seller Co', credits: 1);
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        $this->expectException(InsufficientCreditException::class);

        app(SendCounterOfferAction::class)->execute($negotiation->id, $seller->id, $this->terms(90000));
    }

    public function test_sendCounterOffer_withSufficientCredit_deductsAndLedgers(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 100);
        $seller = $this->verifiedBusiness('Seller Co', credits: 100);
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        app(SendCounterOfferAction::class)->execute($negotiation->id, $seller->id, $this->terms(90000));

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($seller->id);
        $this->assertSame(98, $balance->balance());
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $seller->id,
            'reason' => 'nexus.negotiation.counter',
            'amount' => 2,
        ]);
    }

    public function test_acceptDeal_withInsufficientCredit_throws(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 21);
        $seller = $this->verifiedBusiness('Seller Co', credits: 100);
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        $this->expectException(InsufficientCreditException::class);

        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
    }

    public function test_acceptDeal_withSufficientCredit_deductsAndLedgersIndependentlyOfContractCharge(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 200);
        $seller = $this->verifiedBusiness('Seller Co', credits: 100);
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());
        // buyer: 200 - 20 (propose) = 180

        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        // buyer: 180 - 2 (accept) - 50 (contract.generate, charged to the
        // initiator by GenerateContractOnNegotiationAcceptedListener) = 128

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($buyer->id);
        $this->assertSame(128, $balance->balance());
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $buyer->id,
            'reason' => 'nexus.negotiation.accept',
            'amount' => 2,
        ]);
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $buyer->id,
            'reason' => 'contract.generate',
            'amount' => 50,
        ]);
    }

    public function test_rejectDeal_withInsufficientCredit_throws(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 100);
        $seller = $this->verifiedBusiness('Seller Co', credits: 1);
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        $this->expectException(InsufficientCreditException::class);

        app(RejectDealAction::class)->execute($negotiation->id, $seller->id, 'too expensive');
    }

    public function test_rejectDeal_withSufficientCredit_deductsAndLedgers(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', credits: 100);
        $seller = $this->verifiedBusiness('Seller Co', credits: 100);
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, $this->terms());

        app(RejectDealAction::class)->execute($negotiation->id, $seller->id, 'too expensive');

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($seller->id);
        $this->assertSame(98, $balance->balance());
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $seller->id,
            'reason' => 'nexus.negotiation.reject',
            'amount' => 2,
        ]);
    }
}
