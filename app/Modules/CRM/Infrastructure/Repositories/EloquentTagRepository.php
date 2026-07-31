<?php

namespace App\Modules\CRM\Infrastructure\Repositories;

use App\Modules\CRM\Domain\Entities\Tag as TagEntity;
use App\Modules\CRM\Domain\Repositories\TagRepositoryInterface;
use App\Modules\CRM\Domain\ValueObjects\TagName;
use App\Modules\CRM\Infrastructure\Models\Tag as TagModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class EloquentTagRepository implements TagRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?TagEntity
    {
        $model = TagModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function nameExists(TagName $name, int $tenantId): bool
    {
        return TagModel::query()->where('tenant_id', $tenantId)->where('name', $name->value())->exists();
    }

    public function save(TagEntity $tag): TagEntity
    {
        $model = $tag->id()
            ? TagModel::query()->where('tenant_id', $tag->tenantId())->findOrFail($tag->id())
            : new TagModel();

        $model->tenant_id = $tag->tenantId();
        $model->name = $tag->name()->value();
        $model->color = $tag->color();
        $model->save();

        return $this->toEntity($model);
    }

    /**
     * A plain query-builder insert against the `customer_tag` pivot
     * table rather than an Eloquent belongsToMany relation — see Tag
     * Model's docblock for why. Idempotent: assigning an already-assigned
     * Tag a second time is a silent no-op, not a duplicate-key error.
     */
    public function assignToCustomer(int $tagId, int $customerId): void
    {
        $alreadyAssigned = DB::table('customer_tag')
            ->where('tag_id', $tagId)
            ->where('customer_id', $customerId)
            ->exists();

        if ($alreadyAssigned) {
            return;
        }

        DB::table('customer_tag')->insert([
            'tag_id' => $tagId,
            'customer_id' => $customerId,
        ]);
    }

    private function toEntity(TagModel $model): TagEntity
    {
        return new TagEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: new TagName($model->name),
            color: $model->color,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
