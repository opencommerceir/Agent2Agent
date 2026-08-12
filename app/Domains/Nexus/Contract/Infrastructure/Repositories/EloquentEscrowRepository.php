<?php

namespace App\Domains\Nexus\Contract\Infrastructure\Repositories;

use App\Domains\Nexus\Contract\Domain\Entities\Escrow as EscrowEntity;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\ValueObjects\EscrowStatus;
use App\Domains\Nexus\Contract\Infrastructure\Models\Escrow as EscrowModel;
use DateTimeImmutable;

class EloquentEscrowRepository implements EscrowRepositoryInterface
{
    public function findById(int $id): ?EscrowEntity
    {
        $model = EscrowModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByContractId(int $contractId): ?EscrowEntity
    {
        $model = EscrowModel::query()->where('contract_id', $contractId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByNegotiationId(int $negotiationId): ?EscrowEntity
    {
        $model = EscrowModel::query()->where('negotiation_id', $negotiationId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStatus(EscrowStatus $status): array
    {
        return EscrowModel::query()
            ->where('status', $status->value)
            ->orderBy('held_at')
            ->get()
            ->map(fn (EscrowModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(EscrowEntity $escrow): EscrowEntity
    {
        $model = $escrow->id()
            ? EscrowModel::query()->findOrFail($escrow->id())
            : new EscrowModel();

        $model->contract_id = $escrow->contractId();
        $model->negotiation_id = $escrow->negotiationId();
        $model->business_a_id = $escrow->businessAId();
        $model->business_b_id = $escrow->businessBId();
        $model->gross_amount = $escrow->grossAmount();
        $model->currency = $escrow->currency();
        $model->platform_fee_percent = $escrow->platformFeePercent();
        $model->platform_fee_amount = $escrow->platformFeeAmount();
        $model->net_amount = $escrow->netAmount();
        $model->status = $escrow->status()->value;
        $model->dispute_reason = $escrow->disputeReason();
        $model->held_at = $escrow->heldAt();
        $model->released_at = $escrow->releasedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(EscrowModel $model): EscrowEntity
    {
        return EscrowEntity::reconstruct(
            id: $model->id,
            contractId: $model->contract_id,
            negotiationId: $model->negotiation_id,
            businessAId: $model->business_a_id,
            businessBId: $model->business_b_id,
            grossAmount: $model->gross_amount,
            currency: $model->currency,
            platformFeePercent: $model->platform_fee_percent,
            platformFeeAmount: $model->platform_fee_amount,
            netAmount: $model->net_amount,
            status: EscrowStatus::from($model->status),
            disputeReason: $model->dispute_reason,
            heldAt: DateTimeImmutable::createFromInterface($model->held_at),
            releasedAt: $model->released_at ? DateTimeImmutable::createFromInterface($model->released_at) : null,
        );
    }
}
