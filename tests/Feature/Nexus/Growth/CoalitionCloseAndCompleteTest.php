<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\CancelCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\CloseCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\CreateCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\GetCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\JoinCoalitionAction;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CoalitionCloseAndCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_belowMinParticipants_throws(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 3, 10.0, 5,
        );

        $this->expectException(InvalidArgumentException::class);

        app(CloseCoalitionAction::class)->execute($coalition->id, $organizer->id);
    }

    public function test_close_byNonOrganizer_throws(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $joiner = $this->verifiedBusiness('Joiner Co', 100);
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 2, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);

        $this->expectException(InvalidArgumentException::class);

        app(CloseCoalitionAction::class)->execute($coalition->id, $joiner->id);
    }

    public function test_close_aggregatesQuantityAppliesDiscountAndOpensRealNegotiation(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co', 100);
        $joiner = $this->verifiedBusiness('Joiner Co', 100);
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 2, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);

        $closed = app(CloseCoalitionAction::class)->execute($coalition->id, $organizer->id);

        $this->assertSame('negotiating', $closed->status);
        $this->assertNotNull($closed->negotiationId);

        $negotiation = app(NegotiationRepositoryInterface::class)->findById($closed->negotiationId);
        $this->assertSame($organizer->id, $negotiation->initiatorBusinessId());
        $this->assertSame($target->id, $negotiation->counterpartyBusinessId());
        $this->assertSame(9000, $negotiation->currentTerms()->price()->amount()); // 10000 * (1 - 10%)
        $this->assertSame(8, $negotiation->currentTerms()->quantity()); // 5 + 3
    }

    public function test_negotiationAcceptance_completesCoalition(): void
    {
        // Organizer ends up paying coalition.create + negotiation.propose
        // (both fire during close()) + contract.generate + escrow.hold
        // (both fire on the initiator once the target accepts) — a
        // generous top-up, same reasoning CostGateIntegrationTest's own
        // fixture already documents.
        $organizer = $this->verifiedBusiness('Organizer Co', 100000);
        $target = $this->verifiedBusiness('Target Co', 100000);
        $joiner = $this->verifiedBusiness('Joiner Co', 100000);
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 2, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);
        $closed = app(CloseCoalitionAction::class)->execute($coalition->id, $organizer->id);

        app(AcceptDealAction::class)->execute($closed->negotiationId, $target->id);

        $final = app(GetCoalitionAction::class)->execute($coalition->id);
        $this->assertSame('completed', $final->status);
    }

    public function test_unrelatedNegotiationAcceptance_doesNotAffectCoalitions(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', 100000);
        $seller = $this->verifiedBusiness('Seller Co', 100000);

        $negotiation = app(\App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new \App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms(
                \App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money::fromAmount(1000, 'IRT'), 1, null
            ),
        );

        app(AcceptDealAction::class)->execute($negotiation->id, $seller->id);

        // No exception, no Coalition touched — this negotiation has no Coalition.
        $this->assertTrue(true);
    }

    public function test_cancel_fromNegotiating_byOrganizer(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co', 100);
        $joiner = $this->verifiedBusiness('Joiner Co', 100);
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 2, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);
        app(CloseCoalitionAction::class)->execute($coalition->id, $organizer->id);

        $cancelled = app(CancelCoalitionAction::class)->execute($coalition->id, $organizer->id);

        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_cancel_byNonOrganizer_throws(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 2, 10.0, 5,
        );

        $this->expectException(InvalidArgumentException::class);

        app(CancelCoalitionAction::class)->execute($coalition->id, $target->id);
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
