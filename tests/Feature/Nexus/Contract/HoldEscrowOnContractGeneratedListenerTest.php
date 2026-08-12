<?php

namespace Tests\Feature\Nexus\Contract;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Domain\Repositories\ContractRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real, non-faked event chain: AcceptDealAction -> NegotiationWasAccepted
 * -> GenerateContractOnNegotiationAcceptedListener -> ContractWasGenerated
 * -> HoldEscrowOnContractGeneratedListener -> HoldEscrowAction. No mocking
 * of any listener — proves the whole Phase 2/Phase 3 event chain still
 * links up end to end.
 */
class HoldEscrowOnContractGeneratedListenerTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    public function test_acceptingANegotiation_holdsAnEscrowWithComputedFee(): void
    {
        config(['nexus.platform.margin.transaction_fee_percent' => 0.5]);
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 2, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $contract = app(ContractRepositoryInterface::class)->findByNegotiationId($negotiation->id);
        $escrow = app(EscrowRepositoryInterface::class)->findByContractId($contract->id());

        $this->assertNotNull($escrow);
        $this->assertSame(2_000_000, $escrow->grossAmount());
        $this->assertSame('IRT', $escrow->currency());
        $this->assertSame(0.5, $escrow->platformFeePercent());
        $this->assertSame(10_000, $escrow->platformFeeAmount());
        $this->assertSame(1_990_000, $escrow->netAmount());
        $this->assertSame('held', $escrow->status()->value);
        $this->assertSame($buyer->id, $escrow->businessAId());
        $this->assertSame($seller->id, $escrow->businessBId());

        // 100cr contract.escrow.hold charged to the initiator (buyer).
        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $buyer->id,
            'reason' => 'contract.escrow.hold',
            'amount' => 100,
        ]);
        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($buyer->id);
        // 100000 - 20 (propose) - 2 (accept) - 50 (contract.generate) - 100 (escrow.hold)
        $this->assertSame(100000 - 20 - 2 - 50 - 100, $balance->balance());
    }

    public function test_rejectingANegotiation_holdsNoEscrow(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );

        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiation->id);

        $this->assertNull($escrow);
    }
}
