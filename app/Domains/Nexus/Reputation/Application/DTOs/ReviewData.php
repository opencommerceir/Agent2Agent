<?php

namespace App\Domains\Nexus\Reputation\Application\DTOs;

use App\Domains\Nexus\Reputation\Domain\Entities\Review;

/**
 * Structured data transfer for Review across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class ReviewData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $negotiationId,
        public readonly int $reviewerBusinessId,
        public readonly int $revieweeBusinessId,
        public readonly int $rating,
        public readonly ?string $comment,
        public readonly string $status,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(Review $review): self
    {
        return new self(
            id: $review->id(),
            negotiationId: $review->negotiationId(),
            reviewerBusinessId: $review->reviewerBusinessId(),
            revieweeBusinessId: $review->revieweeBusinessId(),
            rating: $review->rating(),
            comment: $review->comment(),
            status: $review->status()->value,
            createdAt: $review->createdAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: ?int, negotiationId: int, reviewerBusinessId: int, revieweeBusinessId: int, rating: int, comment: ?string, status: string, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'negotiationId' => $this->negotiationId,
            'reviewerBusinessId' => $this->reviewerBusinessId,
            'revieweeBusinessId' => $this->revieweeBusinessId,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
        ];
    }
}
