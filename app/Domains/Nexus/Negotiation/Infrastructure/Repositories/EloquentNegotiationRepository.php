<?php

namespace App\Domains\Nexus\Negotiation\Infrastructure\Repositories;

use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation as NegotiationEntity;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Negotiation\Infrastructure\Models\Negotiation as NegotiationModel;
use DateTimeImmutable;

class EloquentNegotiationRepository implements NegotiationRepositoryInterface
{
    public function findById(int $id): ?NegotiationEntity
    {
        $model = NegotiationModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findVisibleTo(int $businessId): array
    {
        return NegotiationModel::query()
            ->where('initiator_business_id', $businessId)
            ->orWhere('counterparty_business_id', $businessId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (NegotiationModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findAll(): array
    {
        return NegotiationModel::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (NegotiationModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(NegotiationEntity $negotiation): NegotiationEntity
    {
        $model = $negotiation->id()
            ? NegotiationModel::query()->findOrFail($negotiation->id())
            : new NegotiationModel();

        $model->initiator_business_id = $negotiation->initiatorBusinessId();
        $model->initiator_tenant_id = $negotiation->initiatorTenantId();
        $model->counterparty_business_id = $negotiation->counterpartyBusinessId();
        $model->counterparty_tenant_id = $negotiation->counterpartyTenantId();
        $model->catalog_item_type = $negotiation->catalogItemType()->value;
        $model->catalog_item_id = $negotiation->catalogItemId();
        $model->status = $negotiation->status()->value;
        $model->current_terms = $negotiation->currentTerms()->toArray();
        $model->round_count = $negotiation->roundCount();
        $model->max_rounds = $negotiation->maxRounds();
        $model->rejection_reason = $negotiation->rejectionReason();
        $model->pending_approval_business_id = $negotiation->pendingApprovalBusinessId();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NegotiationModel $model): NegotiationEntity
    {
        return new NegotiationEntity(
            id: $model->id,
            initiatorBusinessId: $model->initiator_business_id,
            initiatorTenantId: $model->initiator_tenant_id,
            counterpartyBusinessId: $model->counterparty_business_id,
            counterpartyTenantId: $model->counterparty_tenant_id,
            catalogItemType: CatalogItemType::from($model->catalog_item_type),
            catalogItemId: $model->catalog_item_id,
            status: NegotiationStatus::from($model->status),
            currentTerms: NegotiationTerms::fromArray($model->current_terms),
            roundCount: $model->round_count,
            maxRounds: $model->max_rounds,
            rejectionReason: $model->rejection_reason,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: DateTimeImmutable::createFromInterface($model->updated_at),
            pendingApprovalBusinessId: $model->pending_approval_business_id,
        );
    }
}
