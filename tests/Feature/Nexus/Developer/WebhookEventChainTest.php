<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Developer\Application\Actions\CreateWebhookSubscriptionAction;
use App\Domains\Nexus\Developer\Application\Actions\DispatchWebhookEventAction;
use App\Domains\Nexus\Developer\Application\Actions\ListWebhookDeliveriesAction;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookDeliveryLogRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real chain: propose -> accept (fires NegotiationWasAccepted) ->
 * Contract auto-generated (fires ContractWasGenerated) -> Escrow
 * auto-held -> release (fires EscrowWasReleased) — no Event::fake(), the
 * same "real chain, not mocked events" discipline Phase 2/M6 and Phase
 * 3/M4's own end-to-end tests already established; only the outbound HTTP
 * layer is mocked (MockHandler), never the domain event dispatch itself.
 */
class WebhookEventChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullDealChain_deliversAllThreeSubscribedEvents(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');

        app(CreateWebhookSubscriptionAction::class)->execute($buyer->id, 'https://buyer.example.com/hook', [
            WebhookEvent::NegotiationAccepted,
            WebhookEvent::ContractGenerated,
            WebhookEvent::EscrowReleased,
        ]);

        $mock = new MockHandler([new Response(200), new Response(200), new Response(200)]);
        $this->app->bind(DispatchWebhookEventAction::class, fn ($app) => new DispatchWebhookEventAction(
            $app->make(WebhookSubscriptionRepositoryInterface::class),
            $app->make(WebhookDeliveryLogRepositoryInterface::class),
            new Client(['handler' => HandlerStack::create($mock)]),
        ));

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);

        $deliveries = app(ListWebhookDeliveriesAction::class)->execute($buyer->id);

        $this->assertCount(3, $deliveries);
        $this->assertEqualsCanonicalizing(
            ['negotiation.accepted', 'contract.generated', 'escrow.released'],
            array_map(fn ($delivery) => $delivery->event, $deliveries),
        );
        foreach ($deliveries as $delivery) {
            $this->assertTrue($delivery->succeeded);
        }
    }

    public function test_sellerWithNoSubscription_receivesNoDeliveries(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');

        $mock = new MockHandler([new Response(200)]);
        $this->app->bind(DispatchWebhookEventAction::class, fn ($app) => new DispatchWebhookEventAction(
            $app->make(WebhookSubscriptionRepositoryInterface::class),
            $app->make(WebhookDeliveryLogRepositoryInterface::class),
            new Client(['handler' => HandlerStack::create($mock)]),
        ));

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $this->assertCount(0, app(ListWebhookDeliveriesAction::class)->execute($seller->id));
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
