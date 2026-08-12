<?php

namespace App\Domains\Nexus\Reputation\Application\Actions;

use App\Domains\Nexus\Reputation\Application\DTOs\ReviewData;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — the flag was reviewed and rejected, so the review goes back to
 * Published. Mirrors RefundEscrowAction's shape: no acting-business
 * parameter at all, authorization is entirely the route guard's job.
 */
final class RestoreReviewAction
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {
    }

    public function execute(int $reviewId): ReviewData
    {
        $review = $this->reviews->findById($reviewId);

        if (! $review) {
            throw new InvalidArgumentException("Review [{$reviewId}] does not exist.");
        }

        $review->restore();

        return ReviewData::fromEntity($this->reviews->save($review));
    }
}
