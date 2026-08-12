<?php

namespace Tests\Unit\Nexus\Reputation;

use App\Domains\Nexus\Reputation\Domain\Entities\Review;
use App\Domains\Nexus\Reputation\Domain\Exceptions\InvalidReviewStateException;
use App\Domains\Nexus\Reputation\Domain\ValueObjects\ReviewStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 * Review is framework-free by design (Domain Layer Rules).
 */
class ReviewTest extends TestCase
{
    public function test_submit_createsPublishedReview(): void
    {
        $review = Review::submit(negotiationId: 1, reviewerBusinessId: 10, revieweeBusinessId: 20, rating: 5, comment: 'great');

        $this->assertSame(ReviewStatus::Published, $review->status());
        $this->assertSame(5, $review->rating());
        $this->assertSame('great', $review->comment());
    }

    public function test_submit_reviewingSelf_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Review::submit(negotiationId: 1, reviewerBusinessId: 10, revieweeBusinessId: 10, rating: 5, comment: null);
    }

    public function test_submit_ratingOutOfRange_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Review::submit(negotiationId: 1, reviewerBusinessId: 10, revieweeBusinessId: 20, rating: 6, comment: null);
    }

    public function test_flag_thenRestore_reachesPublished(): void
    {
        $review = Review::submit(negotiationId: 1, reviewerBusinessId: 10, revieweeBusinessId: 20, rating: 5, comment: null);

        $review->flag();
        $this->assertSame(ReviewStatus::Flagged, $review->status());

        $review->restore();
        $this->assertSame(ReviewStatus::Published, $review->status());
    }

    public function test_flag_thenRemove_reachesRemoved(): void
    {
        $review = Review::submit(negotiationId: 1, reviewerBusinessId: 10, revieweeBusinessId: 20, rating: 1, comment: null);

        $review->flag();
        $review->remove();

        $this->assertSame(ReviewStatus::Removed, $review->status());
    }

    public function test_remove_onPublishedReview_throws(): void
    {
        $review = Review::submit(negotiationId: 1, reviewerBusinessId: 10, revieweeBusinessId: 20, rating: 1, comment: null);

        $this->expectException(InvalidReviewStateException::class);

        $review->remove();
    }

    public function test_flag_onRemovedReview_throws(): void
    {
        $review = Review::submit(negotiationId: 1, reviewerBusinessId: 10, revieweeBusinessId: 20, rating: 1, comment: null);
        $review->flag();
        $review->remove();

        $this->expectException(InvalidReviewStateException::class);

        $review->flag();
    }
}
