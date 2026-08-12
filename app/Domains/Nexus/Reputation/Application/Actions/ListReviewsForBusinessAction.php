<?php

namespace App\Domains\Nexus\Reputation\Application\Actions;

use App\Domains\Nexus\Reputation\Application\DTOs\ReviewData;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;

/**
 * Published reviews only — Flagged/Removed reviews are a moderation
 * concern, never shown to a Business checking a counterparty's standing
 * (matches RankSuppliersAction's own "only real, earned signals" honesty).
 */
final class ListReviewsForBusinessAction
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {
    }

    /**
     * @return list<ReviewData>
     */
    public function execute(int $revieweeBusinessId): array
    {
        return array_map(
            fn ($review) => ReviewData::fromEntity($review),
            $this->reviews->findPublishedForBusiness($revieweeBusinessId),
        );
    }
}
