<?php

namespace Tests\Feature\Nexus\Reputation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewViewerTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusinessWithOwner(string $nameFa, string $nameEn, string $email): array
    {
        $business = app(RegisterBusinessAction::class)->execute($nameFa, $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => $nameEn.' Owner',
            'email' => $email,
            'password' => 'password123',
        ]);

        return [$business, $owner];
    }

    public function test_submitReview_createsPublishedReview(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);

        $response = $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.review.submit', $negotiation->id), [
            'rating' => 5,
            'comment' => 'great',
        ]);

        $response->assertRedirect(route('nexus.negotiations.show', $negotiation->id));
        $review = app(ReviewRepositoryInterface::class)->findByNegotiationAndReviewer($negotiation->id, $buyer->id);
        $this->assertNotNull($review);
        $this->assertSame(5, $review->rating());
    }

    public function test_show_afterReviewing_displaysAlreadySubmittedMessage(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);
        $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.review.submit', $negotiation->id), ['rating' => 4]);

        $response = $this->actingAs($buyerOwner, 'business')->get(route('nexus.negotiations.show', $negotiation->id));

        $response->assertStatus(200);
        $response->assertSee(t('messages.nexus.negotiation.review.already_submitted'));
    }
}
