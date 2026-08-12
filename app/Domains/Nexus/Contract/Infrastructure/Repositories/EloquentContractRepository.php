<?php

namespace App\Domains\Nexus\Contract\Infrastructure\Repositories;

use App\Domains\Nexus\Contract\Domain\Entities\Contract as ContractEntity;
use App\Domains\Nexus\Contract\Domain\Repositories\ContractRepositoryInterface;
use App\Domains\Nexus\Contract\Infrastructure\Models\Contract as ContractModel;
use DateTimeImmutable;

class EloquentContractRepository implements ContractRepositoryInterface
{
    public function findById(int $id): ?ContractEntity
    {
        $model = ContractModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByNegotiationId(int $negotiationId): ?ContractEntity
    {
        $model = ContractModel::query()->where('negotiation_id', $negotiationId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(ContractEntity $contract): ContractEntity
    {
        $model = $contract->id()
            ? ContractModel::query()->findOrFail($contract->id())
            : new ContractModel();

        $model->negotiation_id = $contract->negotiationId();
        $model->business_a_id = $contract->businessAId();
        $model->business_b_id = $contract->businessBId();
        $model->terms = $contract->terms();
        $model->content_hash = $contract->contentHash();
        $model->pdf_path = $contract->pdfPath();
        $model->signed_at = $contract->signedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ContractModel $model): ContractEntity
    {
        return new ContractEntity(
            id: $model->id,
            negotiationId: $model->negotiation_id,
            businessAId: $model->business_a_id,
            businessBId: $model->business_b_id,
            terms: $model->terms,
            contentHash: $model->content_hash,
            pdfPath: $model->pdf_path,
            signedAt: DateTimeImmutable::createFromInterface($model->signed_at),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
