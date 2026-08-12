<?php

namespace App\Domains\Nexus\Admin\Infrastructure\Repositories;

use App\Domains\Nexus\Admin\Domain\Entities\PlatformSetting as PlatformSettingEntity;
use App\Domains\Nexus\Admin\Domain\Repositories\PlatformSettingRepositoryInterface;
use App\Domains\Nexus\Admin\Infrastructure\Models\PlatformSetting as PlatformSettingModel;

class EloquentPlatformSettingRepository implements PlatformSettingRepositoryInterface
{
    public function findByKey(string $key): ?PlatformSettingEntity
    {
        $model = PlatformSettingModel::query()->where('key', $key)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(PlatformSettingEntity $setting): PlatformSettingEntity
    {
        $model = $setting->id()
            ? PlatformSettingModel::query()->findOrFail($setting->id())
            : PlatformSettingModel::query()->where('key', $setting->key())->first() ?? new PlatformSettingModel();

        $model->key = $setting->key();
        $model->value = $setting->value();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(PlatformSettingModel $model): PlatformSettingEntity
    {
        return new PlatformSettingEntity(
            id: $model->id,
            key: $model->key,
            value: $model->value,
        );
    }
}
