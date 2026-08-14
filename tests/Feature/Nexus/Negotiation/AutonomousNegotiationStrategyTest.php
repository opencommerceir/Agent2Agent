<?php

namespace Tests\Feature\Nexus\Negotiation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Negotiation\Application\Services\AutonomousNegotiationStrategy;
use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `AutonomousNegotiationStrategy::decide()` — the pure "what price would I
 * accept/counter with" logic the reactive Auto-Responder relies on.
 * Depends on real Product records (list price is the anchor), so a Feature
 * test with RefreshDatabase, same as every other Nexus Action/Service
 * that touches a repository.
 */
class AutonomousNegotiationStrategyTest extends TestCase
{
    use RefreshDatabase;

    private function sellerWithProduct(int $listPrice): array
    {
        $seller = app(RegisterBusinessAction::class)->execute('فروشنده', 'Seller', BusinessType::Company, Industry::Manufacturing);
        app(VerifyBusinessAction::class)->execute($seller->id);
        $product = app(AddProductAction::class)->execute($seller->id, 'کالا', 'Widget', $listPrice, 'IRT', 10);

        return [$seller->id, $product->id];
    }

    private function negotiation(int $sellerId, int $buyerId, int $productId, int $roundCount = 1, int $maxRounds = 5): Negotiation
    {
        return new Negotiation(
            id: 1,
            initiatorBusinessId: $buyerId,
            initiatorTenantId: 1,
            counterpartyBusinessId: $sellerId,
            counterpartyTenantId: 2,
            catalogItemType: CatalogItemType::Product,
            catalogItemId: $productId,
            status: NegotiationStatus::Proposed,
            currentTerms: new NegotiationTerms(Money::fromAmount(1, 'IRT'), 1, null),
            roundCount: $roundCount,
            maxRounds: $maxRounds,
            rejectionReason: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function test_sellerReceivingOfferWithinTolerance_accepts(): void
    {
        [$sellerId, $productId] = $this->sellerWithProduct(1_000_000);
        $negotiation = $this->negotiation($sellerId, 999, $productId);
        $offer = new NegotiationTerms(Money::fromAmount(900_000, 'IRT'), 1, null);

        $decision = app(AutonomousNegotiationStrategy::class)->decide($negotiation, $offer, $sellerId, 15.0);

        $this->assertSame('accept', $decision['action']);
    }

    public function test_sellerReceivingOfferSlightlyBelowTolerance_counters(): void
    {
        [$sellerId, $productId] = $this->sellerWithProduct(1_000_000);
        $negotiation = $this->negotiation($sellerId, 999, $productId);
        $offer = new NegotiationTerms(Money::fromAmount(800_000, 'IRT'), 1, null);

        $decision = app(AutonomousNegotiationStrategy::class)->decide($negotiation, $offer, $sellerId, 15.0);

        $this->assertSame('counter', $decision['action']);
        // A seller's counter must never go below its own acceptable floor (85% of list).
        $this->assertGreaterThanOrEqual(850_000, $decision['terms']->price()->amount());
        $this->assertLessThan(1_000_000, $decision['terms']->price()->amount());
    }

    public function test_sellerReceivingOfferWayBelowTolerance_rejects(): void
    {
        [$sellerId, $productId] = $this->sellerWithProduct(1_000_000);
        $negotiation = $this->negotiation($sellerId, 999, $productId);
        $offer = new NegotiationTerms(Money::fromAmount(300_000, 'IRT'), 1, null);

        $decision = app(AutonomousNegotiationStrategy::class)->decide($negotiation, $offer, $sellerId, 15.0);

        $this->assertSame('reject', $decision['action']);
    }

    public function test_buyerReceivingCounterAboveTolerance_rejects(): void
    {
        [$sellerId, $productId] = $this->sellerWithProduct(1_000_000);
        $buyer = app(RegisterBusinessAction::class)->execute('خریدار', 'Buyer', BusinessType::Company, Industry::Manufacturing);
        $negotiation = $this->negotiation($sellerId, $buyer->id, $productId);
        $counter = new NegotiationTerms(Money::fromAmount(2_000_000, 'IRT'), 1, null);

        $decision = app(AutonomousNegotiationStrategy::class)->decide($negotiation, $counter, $buyer->id, 15.0);

        $this->assertSame('reject', $decision['action']);
    }

    public function test_atRoundLimit_rejectsInsteadOfCounteringForever(): void
    {
        [$sellerId, $productId] = $this->sellerWithProduct(1_000_000);
        $negotiation = $this->negotiation($sellerId, 999, $productId, roundCount: 5, maxRounds: 5);
        $offer = new NegotiationTerms(Money::fromAmount(800_000, 'IRT'), 1, null);

        $decision = app(AutonomousNegotiationStrategy::class)->decide($negotiation, $offer, $sellerId, 15.0);

        $this->assertSame('reject', $decision['action']);
    }
}
