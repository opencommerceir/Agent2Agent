<?php

namespace App\Domains\Nexus\Reputation\Domain\Repositories;

use App\Domains\Nexus\Reputation\Domain\Entities\Review;

interface ReviewRepositoryInterface
{
    public function findById(int $id): ?Review;

    public function findByNegotiationAndReviewer(int $negotiationId, int $reviewerBusinessId): ?Review;

    /**
     * @return list<Review>
     */
    public function findPublishedForBusiness(int $revieweeBusinessId): array;

    public function save(Review $review): Review;
}
