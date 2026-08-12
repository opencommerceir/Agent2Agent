<?php

namespace App\Domains\Nexus\Contract\Infrastructure\Repositories;

use App\Domains\Nexus\Contract\Domain\Entities\DisputeCase as DisputeCaseEntity;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\ValueObjects\DisputeCaseStatus;
use App\Domains\Nexus\Contract\Infrastructure\Models\DisputeCase as DisputeCaseModel;
use DateTimeImmutable;

class EloquentDisputeCaseRepository implements DisputeCaseRepositoryInterface
{
    public function findById(int $id): ?DisputeCaseEntity
    {
        $model = DisputeCaseModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByEscrowId(int $escrowId): ?DisputeCaseEntity
    {
        $model = DisputeCaseModel::query()->where('escrow_id', $escrowId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStatus(DisputeCaseStatus $status): array
    {
        return DisputeCaseModel::query()
            ->where('status', $status->value)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DisputeCaseModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(DisputeCaseEntity $disputeCase): DisputeCaseEntity
    {
        $model = $disputeCase->id()
            ? DisputeCaseModel::query()->findOrFail($disputeCase->id())
            : new DisputeCaseModel();

        $model->escrow_id = $disputeCase->escrowId();
        $model->negotiation_id = $disputeCase->negotiationId();
        $model->business_a_id = $disputeCase->businessAId();
        $model->business_b_id = $disputeCase->businessBId();
        $model->opened_by_business_id = $disputeCase->openedByBusinessId();
        $model->reason = $disputeCase->reason();
        $model->evidence = $disputeCase->evidence();
        $model->status = $disputeCase->status()->value;
        $model->resolution = $disputeCase->resolution();
        $model->resolved_at = $disputeCase->resolvedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(DisputeCaseModel $model): DisputeCaseEntity
    {
        return DisputeCaseEntity::reconstruct(
            id: $model->id,
            escrowId: $model->escrow_id,
            negotiationId: $model->negotiation_id,
            businessAId: $model->business_a_id,
            businessBId: $model->business_b_id,
            openedByBusinessId: $model->opened_by_business_id,
            reason: $model->reason,
            evidence: $model->evidence ?? [],
            status: DisputeCaseStatus::from($model->status),
            resolution: $model->resolution,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            resolvedAt: $model->resolved_at ? DateTimeImmutable::createFromInterface($model->resolved_at) : null,
        );
    }
}
