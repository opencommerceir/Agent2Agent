<?php

namespace Tests\Feature\Nexus\Marketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\CreateCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\JoinCoalitionAction;
use App\Domains\Nexus\Marketplace\Application\Actions\GetBusinessNetworkAction;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetBusinessNetworkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withNoRelationships_returnsOnlySelf(): void
    {
        $business = $this->verifiedBusiness('Lonely Co');

        $network = app(GetBusinessNetworkAction::class)->execute($business->id);

        $this->assertCount(1, $network->nodes);
        $this->assertSame('self', $network->nodes[0]['relation']);
        $this->assertSame([], $network->edges);
    }

    public function test_execute_withAcceptedNegotiation_includesDirectPartner(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', 100000);
        $seller = $this->verifiedBusiness('Seller Co', 100000);
        $this->acceptedNegotiationBetween($buyer->id, $seller->id);

        $network = app(GetBusinessNetworkAction::class)->execute($buyer->id);

        $relations = array_column($network->nodes, 'relation', 'businessId');
        $this->assertSame('direct', $relations[$seller->id]);
        $this->assertContains(['from' => $buyer->id, 'to' => $seller->id, 'type' => 'negotiated'], $network->edges);
    }

    public function test_execute_withPendingNegotiation_doesNotIncludeIt(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', 100000);
        $seller = $this->verifiedBusiness('Seller Co', 100000);
        app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1000, 'IRT'), 1, null),
        );

        $network = app(GetBusinessNetworkAction::class)->execute($buyer->id);

        $this->assertCount(1, $network->nodes); // only self — negotiation never accepted
    }

    public function test_execute_withCoalitionMembership_includesCoalitionMate(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100000);
        $target = $this->verifiedBusiness('Target Co', 100000);
        $joiner = $this->verifiedBusiness('Joiner Co', 100000);
        $coalition = app(CreateCoalitionAction::class)->execute(
            $organizer->id, $target->id, CatalogItemType::Product, 1, 10000, 'IRT', 2, 10.0, 5,
        );
        app(JoinCoalitionAction::class)->execute($coalition->id, $joiner->id, 3);

        $network = app(GetBusinessNetworkAction::class)->execute($organizer->id);

        $relations = array_column($network->nodes, 'relation', 'businessId');
        $this->assertSame('coalition', $relations[$joiner->id]);
    }

    public function test_execute_recommendsPartnersOfPartners(): void
    {
        $a = $this->verifiedBusiness('A Co', 100000);
        $b = $this->verifiedBusiness('B Co', 100000);
        $c = $this->verifiedBusiness('C Co', 100000);
        $this->acceptedNegotiationBetween($a->id, $b->id);
        $this->acceptedNegotiationBetween($b->id, $c->id);

        $network = app(GetBusinessNetworkAction::class)->execute($a->id);

        $relations = array_column($network->nodes, 'relation', 'businessId');
        $this->assertSame('direct', $relations[$b->id]);
        $this->assertSame('recommended', $relations[$c->id]);

        $parents = array_column($network->nodes, 'parentBusinessId', 'businessId');
        $this->assertSame($b->id, $parents[$c->id]);
    }

    public function test_execute_directPartnerNeverAlsoListedAsRecommended(): void
    {
        // A-B and A-C both direct, and B-C also direct — C must not appear
        // twice (once as direct, once as recommended via B).
        $a = $this->verifiedBusiness('A Co', 100000);
        $b = $this->verifiedBusiness('B Co', 100000);
        $c = $this->verifiedBusiness('C Co', 100000);
        $this->acceptedNegotiationBetween($a->id, $b->id);
        $this->acceptedNegotiationBetween($a->id, $c->id);
        $this->acceptedNegotiationBetween($b->id, $c->id);

        $network = app(GetBusinessNetworkAction::class)->execute($a->id);

        $businessIds = array_column($network->nodes, 'businessId');
        $this->assertSame(count($businessIds), count(array_unique($businessIds)));
    }

    private function acceptedNegotiationBetween(int $initiatorId, int $counterpartyId): void
    {
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $initiatorId, $counterpartyId, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $counterpartyId);
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
