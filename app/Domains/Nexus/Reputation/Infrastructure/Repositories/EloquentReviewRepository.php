<?php

namespace App\Domains\Nexus\Reputation\Infrastructure\Repositories;

use App\Domains\Nexus\Reputation\Domain\Entities\Review as ReviewEntity;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use App\Domains\Nexus\Reputation\Domain\ValueObjects\ReviewStatus;
use App\Domains\Nexus\Reputation\Infrastructure\Models\Review as ReviewModel;
use DateTimeImmutable;

class EloquentReviewRepository implements ReviewRepositoryInterface
{
    public function findById(int $id): ?ReviewEntity
    {
        $model = ReviewModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByNegotiationAndReviewer(int $negotiationId, int $reviewerBusinessId): ?ReviewEntity
    {
        $model = ReviewModel::query()
            ->where('negotiation_id', $negotiationId)
            ->where('reviewer_business_id', $reviewerBusinessId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findPublishedForBusiness(int $revieweeBusinessId): array
    {
        return ReviewModel::query()
            ->where('reviewee_business_id', $revieweeBusinessId)
            ->where('status', ReviewStatus::Published->value)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReviewModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ReviewEntity $review): ReviewEntity
    {
        $model = $review->id()
            ? ReviewModel::query()->findOrFail($review->id())
            : new ReviewModel();

        $model->negotiation_id = $review->negotiationId();
        $model->reviewer_business_id = $review->reviewerBusinessId();
        $model->reviewee_business_id = $review->revieweeBusinessId();
        $model->rating = $review->rating();
        $model->comment = $review->comment();
        $model->status = $review->status()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ReviewModel $model): ReviewEntity
    {
        return ReviewEntity::reconstruct(
            id: $model->id,
            negotiationId: $model->negotiation_id,
            reviewerBusinessId: $model->reviewer_business_id,
            revieweeBusinessId: $model->reviewee_business_id,
            rating: $model->rating,
            comment: $model->comment,
            status: ReviewStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
