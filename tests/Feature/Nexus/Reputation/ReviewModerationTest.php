<?php

namespace Tests\Feature\Nexus\Reputation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Reputation\Application\Actions\FlagReviewAction;
use App\Domains\Nexus\Reputation\Application\Actions\RemoveReviewAction;
use App\Domains\Nexus\Reputation\Application\Actions\RestoreReviewAction;
use App\Domains\Nexus\Reputation\Application\Actions\SubmitReviewAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    private function publishedReview(): array
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);
        $review = app(SubmitReviewAction::class)->execute($negotiation->id, $buyer->id, 1, 'unfair review');

        return [$buyer, $seller, $review->id];
    }

    public function test_flag_byReviewee_movesToFlagged(): void
    {
        [, $seller, $reviewId] = $this->publishedReview();

        $result = app(FlagReviewAction::class)->execute($reviewId, $seller->id);

        $this->assertSame('flagged', $result->status);
    }

    public function test_flag_byReviewer_throws(): void
    {
        [$buyer, , $reviewId] = $this->publishedReview();

        $this->expectException(InvalidArgumentException::class);

        app(FlagReviewAction::class)->execute($reviewId, $buyer->id);
    }

    public function test_restore_movesFlaggedBackToPublished(): void
    {
        [, $seller, $reviewId] = $this->publishedReview();
        app(FlagReviewAction::class)->execute($reviewId, $seller->id);

        $result = app(RestoreReviewAction::class)->execute($reviewId);

        $this->assertSame('published', $result->status);
    }

    public function test_remove_movesFlaggedToRemoved(): void
    {
        [, $seller, $reviewId] = $this->publishedReview();
        app(FlagReviewAction::class)->execute($reviewId, $seller->id);

        $result = app(RemoveReviewAction::class)->execute($reviewId);

        $this->assertSame('removed', $result->status);
    }

    public function test_remove_onPublishedReview_throws(): void
    {
        [, , $reviewId] = $this->publishedReview();

        $this->expectException(\App\Domains\Nexus\Reputation\Domain\Exceptions\InvalidReviewStateException::class);

        app(RemoveReviewAction::class)->execute($reviewId);
    }
}
