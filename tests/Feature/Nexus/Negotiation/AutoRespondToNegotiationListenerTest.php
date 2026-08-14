<?php

namespace Tests\Feature\Nexus\Negotiation;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reactive Autonomous Agent Runtime, end to end: a real
 * InitiateNegotiationAction call, with no further manual action from the
 * test, cascading through AutoRespondToNegotiationListener until the
 * Negotiation resolves on its own — the same behavior a real external
 * Agent calling MCP would trigger without a human/software client on the
 * other side at all.
 *
 * `autoRespondEnabled()` defaults to false (opt-in) — confirmed necessary
 * by running the full existing Negotiation-adjacent suite with a
 * default-true build, which broke 32 tests across 18 unrelated files that
 * use a manually-driven Negotiation as fixture setup. These tests
 * therefore explicitly opt individual Businesses in via
 * `Agent::setStrategies(['auto_respond' => true])`.
 */
class AutoRespondToNegotiationListenerTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusinessWithProduct(string $nameFa, string $nameEn, int $listPrice, bool $autoRespond = false): array
    {
        $business = app(RegisterBusinessAction::class)->execute($nameFa, $nameEn, BusinessType::Company, Industry::Manufacturing);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');
        $product = app(AddProductAction::class)->execute($business->id, 'کالا', 'Widget', $listPrice, 'IRT', 10);

        if ($autoRespond) {
            $this->enableAutoRespond($business->id);
        }

        return [$business->id, $product->id];
    }

    private function verifiedBusiness(string $nameFa, string $nameEn, bool $autoRespond = false): int
    {
        $business = app(RegisterBusinessAction::class)->execute($nameFa, $nameEn, BusinessType::Company, Industry::Manufacturing);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        if ($autoRespond) {
            $this->enableAutoRespond($business->id);
        }

        return $business->id;
    }

    private function enableAutoRespond(int $businessId): void
    {
        $agents = app(AgentRepositoryInterface::class);
        $agent = $agents->findByBusinessId($businessId);
        $agent->setStrategies(['auto_respond' => true]);
        $agents->save($agent);
    }

    public function test_offerWithinTolerance_autoAcceptsWithoutAnyFurtherManualCall(): void
    {
        [$sellerId, $productId] = $this->verifiedBusinessWithProduct('فروشنده', 'Seller', 1_000_000, autoRespond: true);
        $buyerId = $this->verifiedBusiness('خریدار', 'Buyer');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, $productId,
            new NegotiationTerms(Money::fromAmount(950_000, 'IRT'), 1, null),
        );

        $final = app(NegotiationRepositoryInterface::class)->findById($negotiation->id);
        $this->assertSame('accepted', $final->status()->value);
    }

    public function test_offerFarOff_negotiatesThroughCountersToAcceptance(): void
    {
        [$sellerId, $productId] = $this->verifiedBusinessWithProduct('فروشنده', 'Seller', 1_000_000, autoRespond: true);
        $buyerId = $this->verifiedBusiness('خریدار', 'Buyer', autoRespond: true);

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, $productId,
            new NegotiationTerms(Money::fromAmount(700_000, 'IRT'), 1, null),
        );

        $final = app(NegotiationRepositoryInterface::class)->findById($negotiation->id);
        $this->assertSame('accepted', $final->status()->value);
        $this->assertGreaterThan(1, $final->roundCount());

        $messages = app(NegotiationMessageRepositoryInterface::class)->findByNegotiationId($negotiation->id);
        $this->assertGreaterThanOrEqual(3, count($messages));
    }

    public function test_offerImpossiblyLow_autoRejects(): void
    {
        [$sellerId, $productId] = $this->verifiedBusinessWithProduct('فروشنده', 'Seller', 1_000_000, autoRespond: true);
        $buyerId = $this->verifiedBusiness('خریدار', 'Buyer');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, $productId,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null),
        );

        $final = app(NegotiationRepositoryInterface::class)->findById($negotiation->id);
        $this->assertSame('rejected', $final->status()->value);
    }

    public function test_byDefault_autoRespondIsDisabled(): void
    {
        [$sellerId, $productId] = $this->verifiedBusinessWithProduct('فروشنده', 'Seller', 1_000_000);
        $buyerId = $this->verifiedBusiness('خریدار', 'Buyer');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, $productId,
            new NegotiationTerms(Money::fromAmount(950_000, 'IRT'), 1, null),
        );

        $final = app(NegotiationRepositoryInterface::class)->findById($negotiation->id);
        $this->assertSame('proposed', $final->status()->value);

        $messages = app(NegotiationMessageRepositoryInterface::class)->findByNegotiationId($negotiation->id);
        $this->assertCount(1, $messages);
    }
}
