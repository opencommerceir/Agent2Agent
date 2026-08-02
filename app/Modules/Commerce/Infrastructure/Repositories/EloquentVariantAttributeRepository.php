<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\VariantAttribute as VariantAttributeEntity;
use App\Modules\Commerce\Domain\Entities\VariantAttributeValue as VariantAttributeValueEntity;
use App\Modules\Commerce\Domain\Repositories\VariantAttributeRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Models\VariantAttribute as VariantAttributeModel;
use App\Modules\Commerce\Infrastructure\Models\VariantAttributeValue as VariantAttributeValueModel;
use DateTimeImmutable;

/**
 * save() only ever inserts values once (when the attribute itself is
 * new) — VariantAttribute's own values are frozen at creation (that
 * entity's own docblock), so there is no update-path for them to handle.
 */
class EloquentVariantAttributeRepository implements VariantAttributeRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?VariantAttributeEntity
    {
        $model = VariantAttributeModel::query()->with('values')->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function nameExists(string $name, int $tenantId): bool
    {
        return VariantAttributeModel::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->exists();
    }

    public function listByTenant(int $tenantId): array
    {
        return VariantAttributeModel::query()
            ->with('values')
            ->where('tenant_id', $tenantId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (VariantAttributeModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(VariantAttributeEntity $attribute): VariantAttributeEntity
    {
        $isNew = $attribute->id() === null;

        $model = $isNew
            ? new VariantAttributeModel()
            : VariantAttributeModel::query()->where('tenant_id', $attribute->tenantId())->findOrFail($attribute->id());

        $model->tenant_id = $attribute->tenantId();
        $model->name = $attribute->name();
        $model->display_order = $attribute->displayOrder();
        $model->created_at = $attribute->createdAt();
        $model->save();

        if ($isNew) {
            foreach ($attribute->values() as $value) {
                $model->values()->create([
                    'value' => $value->value(),
                    'display_order' => $value->displayOrder(),
                    'created_at' => $value->createdAt(),
                ]);
            }
        }

        return $this->toEntity($model->fresh('values'));
    }

    private function toEntity(VariantAttributeModel $model): VariantAttributeEntity
    {
        $values = $model->values->map(fn (VariantAttributeValueModel $value) => VariantAttributeValueEntity::reconstitute(
            id: $value->id,
            attributeId: $value->attribute_id,
            value: $value->value,
            displayOrder: $value->display_order,
            createdAt: DateTimeImmutable::createFromInterface($value->created_at),
        ))->all();

        return VariantAttributeEntity::reconstitute(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            displayOrder: $model->display_order,
            values: $values,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
