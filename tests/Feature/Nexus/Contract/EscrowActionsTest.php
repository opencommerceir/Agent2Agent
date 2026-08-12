<?php

namespace Tests\Feature\Nexus\Contract;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\DisputeEscrowAction;
use App\Domains\Nexus\Contract\Application\Actions\RefundEscrowAction;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
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

class EscrowActionsTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    /**
     * @return array{0: BusinessData, 1: BusinessData, 2: int}
     */
    private function heldEscrow(): array
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        return [$buyer, $seller, $negotiation->id];
    }

    public function test_release_byEitherParty_transitionsToReleased(): void
    {
        [, $seller, $negotiationId] = $this->heldEscrow();

        $result = app(ReleaseEscrowAction::class)->execute($negotiationId, $seller->id);

        $this->assertSame('released', $result->status);
    }

    public function test_release_byNonParty_throws(): void
    {
        [, , $negotiationId] = $this->heldEscrow();
        $outsider = $this->verifiedBusiness('Outsider Co');

        $this->expectException(InvalidArgumentException::class);

        app(ReleaseEscrowAction::class)->execute($negotiationId, $outsider->id);
    }

    public function test_dispute_recordsReasonAndTransitionsToDisputed(): void
    {
        [$buyer, , $negotiationId] = $this->heldEscrow();

        $result = app(DisputeEscrowAction::class)->execute($negotiationId, $buyer->id, 'never delivered');

        $this->assertSame('disputed', $result->status);
        $this->assertSame('never delivered', $result->disputeReason);
    }

    public function test_refund_byAdmin_transitionsDisputedToRefunded(): void
    {
        [$buyer, , $negotiationId] = $this->heldEscrow();
        app(DisputeEscrowAction::class)->execute($negotiationId, $buyer->id, 'never delivered');
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);

        $result = app(RefundEscrowAction::class)->execute($escrow->id());

        $this->assertSame('refunded', $result->status);
    }

    public function test_refund_onHeldEscrow_throws(): void
    {
        [, , $negotiationId] = $this->heldEscrow();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);

        $this->expectException(InvalidArgumentException::class);

        app(RefundEscrowAction::class)->execute($escrow->id());
    }
}
