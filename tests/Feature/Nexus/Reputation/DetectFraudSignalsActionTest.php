<?php

namespace Tests\Feature\Nexus\Reputation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ArbitrateDisputeAction;
use App\Domains\Nexus\Contract\Application\Actions\DisputeEscrowAction;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Reputation\Application\Actions\DetectFraudSignalsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectFraudSignalsActionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 1_000_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    private function loseDisputeAsSeller(BusinessData $buyer, BusinessData $seller): void
    {
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(DisputeEscrowAction::class)->execute($negotiation->id, $buyer->id, 'never delivered');
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiation->id);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());
        // 'refund_buyer' rules against the seller.
        app(ArbitrateDisputeAction::class)->execute($disputeCase->id(), 'refund_buyer');
    }

    public function test_execute_autoSuspendsBusinessCrossingThreshold(): void
    {
        config(['nexus.platform.reputation.fraud.dispute_loss_threshold' => 3]);
        $seller = $this->verifiedBusiness('Seller Co');

        for ($i = 0; $i < 3; $i++) {
            $buyer = $this->verifiedBusiness("Buyer {$i} Co");
            $this->loseDisputeAsSeller($buyer, $seller);
        }

        $suspended = app(DetectFraudSignalsAction::class)->execute();

        $this->assertSame([$seller->id], $suspended);
        $updated = app(BusinessRepositoryInterface::class)->findById($seller->id);
        $this->assertFalse($updated->isActive());
    }

    public function test_execute_belowThreshold_doesNotSuspend(): void
    {
        config(['nexus.platform.reputation.fraud.dispute_loss_threshold' => 3]);
        $seller = $this->verifiedBusiness('Seller Co');
        $buyer = $this->verifiedBusiness('Buyer Co');
        $this->loseDisputeAsSeller($buyer, $seller);

        $suspended = app(DetectFraudSignalsAction::class)->execute();

        $this->assertSame([], $suspended);
    }

    public function test_execute_isIdempotent_doesNotReSuspendAlreadySuspended(): void
    {
        config(['nexus.platform.reputation.fraud.dispute_loss_threshold' => 3]);
        $seller = $this->verifiedBusiness('Seller Co');
        for ($i = 0; $i < 3; $i++) {
            $buyer = $this->verifiedBusiness("Buyer {$i} Co");
            $this->loseDisputeAsSeller($buyer, $seller);
        }
        app(DetectFraudSignalsAction::class)->execute();

        $secondRun = app(DetectFraudSignalsAction::class)->execute();

        $this->assertSame([], $secondRun);
    }
}
