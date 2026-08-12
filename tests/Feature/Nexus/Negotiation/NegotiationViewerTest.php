<?php

namespace Tests\Feature\Nexus\Negotiation;

use App\Domains\Nexus\Agent\Application\Actions\SetAuthorityLimitsAction;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegotiationViewerTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusinessWithOwner(string $nameFa, string $nameEn, string $email): array
    {
        $business = app(RegisterBusinessAction::class)->execute($nameFa, $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => $nameEn.' Owner',
            'email' => $email,
            'password' => 'password123',
        ]);

        return [$business, $owner];
    }

    public function test_show_forAParty_displaysTheNegotiation(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );

        $response = $this->actingAs($buyerOwner, 'business')->get(route('nexus.negotiations.show', $negotiation->id));

        $response->assertStatus(200);
        $response->assertSee('Seller Co');
    }

    public function test_messages_endpoint_returnsOnlyMessagesAfterGivenId(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );
        $response = $this->actingAs($buyerOwner, 'business')->getJson(
            route('nexus.negotiations.messages', $negotiation->id).'?after=0'
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('messages'));

        $latestMessageId = $response->json('messages.0.id');

        $emptyPoll = $this->actingAs($buyerOwner, 'business')->getJson(
            route('nexus.negotiations.messages', $negotiation->id).'?after='.$latestMessageId
        );
        $emptyPoll->assertStatus(200);
        $this->assertCount(0, $emptyPoll->json('messages'));
    }

    public function test_approve_forPendingNegotiation_movesToAcceptedAndGeneratesContract(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');
        $buyerAgent = app(AgentRepositoryInterface::class)->findByBusinessId($buyer->id);
        app(SetAuthorityLimitsAction::class)->execute($buyerAgent->id(), ['max_deal_value' => 50000]);

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $response = $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.approve', $negotiation->id));

        $response->assertRedirect(route('nexus.negotiations.show', $negotiation->id));
        $this->assertDatabaseHas('negotiations', ['id' => $negotiation->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('contracts', ['negotiation_id' => $negotiation->id]);
    }

    public function test_reject_forPendingNegotiation_movesToRejected(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');
        $buyerAgent = app(AgentRepositoryInterface::class)->findByBusinessId($buyer->id);
        app(SetAuthorityLimitsAction::class)->execute($buyerAgent->id(), ['max_deal_value' => 50000]);

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $response = $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.reject', $negotiation->id), ['reason' => 'changed my mind']);

        $response->assertRedirect(route('nexus.negotiations.show', $negotiation->id));
        $this->assertDatabaseHas('negotiations', ['id' => $negotiation->id, 'status' => 'rejected', 'rejection_reason' => 'changed my mind']);
    }

    public function test_index_listsVisibleNegotiations(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');

        app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(100000, 'IRT'), 1, null),
        );

        $response = $this->actingAs($buyerOwner, 'business')->get(route('nexus.negotiations.index'));

        $response->assertStatus(200);
        $response->assertSee('Seller Co');
    }
}
