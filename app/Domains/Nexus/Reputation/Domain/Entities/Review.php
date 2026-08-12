<?php

namespace App\Domains\Nexus\Reputation\Domain\Entities;

use App\Domains\Nexus\Reputation\Domain\Exceptions\InvalidReviewStateException;
use App\Domains\Nexus\Reputation\Domain\ValueObjects\ReviewStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One side of a completed deal rating the other — bidirectional by
 * construction (the same negotiation produces up to two Review rows, one
 * per direction), never editable after submission (rating/comment are
 * readonly) to keep it an honest record of a moment, not something either
 * side can quietly rewrite after a dispute. Moderation only ever changes
 * `status`, never the content itself — same "immutable fact, mutable
 * workflow state around it" split Escrow/Negotiation already establish.
 *
 * State machine mirrors the codebase-wide ALLOWED_TRANSITIONS +
 * transitionTo() guard shape (Negotiation/Escrow/CreditPurchaseSession).
 * `Removed` is terminal — a removed review stays removed, it doesn't
 * bounce back to Published (that would let a bad-faith flag be silently
 * "un-removed" without a fresh moderation decision).
 */
final class Review
{
    /**
     * @var array<string, list<ReviewStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'published' => [ReviewStatus::Flagged],
        'flagged' => [ReviewStatus::Published, ReviewStatus::Removed],
        'removed' => [],
    ];

    private function __construct(
        private readonly ?int $id,
        private readonly int $negotiationId,
        private readonly int $reviewerBusinessId,
        private readonly int $revieweeBusinessId,
        private readonly int $rating,
        private readonly ?string $comment,
        private ReviewStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function submit(
        int $negotiationId,
        int $reviewerBusinessId,
        int $revieweeBusinessId,
        int $rating,
        ?string $comment,
    ): self {
        if ($reviewerBusinessId === $revieweeBusinessId) {
            throw new InvalidArgumentException('A Business cannot review itself.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Rating must be between 1 and 5.');
        }

        return new self(
            id: null,
            negotiationId: $negotiationId,
            reviewerBusinessId: $reviewerBusinessId,
            revieweeBusinessId: $revieweeBusinessId,
            rating: $rating,
            comment: $comment,
            status: ReviewStatus::Published,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function reconstruct(
        int $id,
        int $negotiationId,
        int $reviewerBusinessId,
        int $revieweeBusinessId,
        int $rating,
        ?string $comment,
        ReviewStatus $status,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            negotiationId: $negotiationId,
            reviewerBusinessId: $reviewerBusinessId,
            revieweeBusinessId: $revieweeBusinessId,
            rating: $rating,
            comment: $comment,
            status: $status,
            createdAt: $createdAt,
        );
    }

    public function flag(): void
    {
        $this->transitionTo(ReviewStatus::Flagged);
    }

    public function restore(): void
    {
        $this->transitionTo(ReviewStatus::Published);
    }

    public function remove(): void
    {
        $this->transitionTo(ReviewStatus::Removed);
    }

    private function transitionTo(ReviewStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidReviewStateException(
                "Review cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function negotiationId(): int
    {
        return $this->negotiationId;
    }

    public function reviewerBusinessId(): int
    {
        return $this->reviewerBusinessId;
    }

    public function revieweeBusinessId(): int
    {
        return $this->revieweeBusinessId;
    }

    public function rating(): int
    {
        return $this->rating;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function status(): ReviewStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
