<?php

namespace App\Modules\CRM\Infrastructure\Repositories;

use App\Modules\CRM\Domain\Entities\CustomerNote as CustomerNoteEntity;
use App\Modules\CRM\Domain\Repositories\CustomerNoteRepositoryInterface;
use App\Modules\CRM\Infrastructure\Models\CustomerNote as CustomerNoteModel;
use DateTimeImmutable;

class EloquentCustomerNoteRepository implements CustomerNoteRepositoryInterface
{
    public function listByCustomer(int $customerId, int $tenantId): array
    {
        return CustomerNoteModel::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->get()
            ->map(fn (CustomerNoteModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(CustomerNoteEntity $note): CustomerNoteEntity
    {
        $model = new CustomerNoteModel();
        $model->tenant_id = $note->tenantId();
        $model->customer_id = $note->customerId();
        $model->agent_id = $note->agentId();
        $model->content = $note->content();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CustomerNoteModel $model): CustomerNoteEntity
    {
        return new CustomerNoteEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            customerId: $model->customer_id,
            agentId: $model->agent_id,
            content: $model->content,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
