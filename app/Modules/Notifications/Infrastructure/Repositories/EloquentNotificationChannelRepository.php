<?php

namespace App\Modules\Notifications\Infrastructure\Repositories;

use App\Modules\Notifications\Domain\Entities\NotificationChannel as NotificationChannelEntity;
use App\Modules\Notifications\Domain\Repositories\NotificationChannelRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Infrastructure\Models\NotificationChannel as NotificationChannelModel;
use DateTimeImmutable;

class EloquentNotificationChannelRepository implements NotificationChannelRepositoryInterface
{
    public function findByType(int $tenantId, ChannelType $channelType): ?NotificationChannelEntity
    {
        $model = NotificationChannelModel::query()
            ->where('tenant_id', $tenantId)
            ->where('channel_type', $channelType->value)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(NotificationChannelEntity $channel): NotificationChannelEntity
    {
        $model = $channel->id()
            ? NotificationChannelModel::query()->where('tenant_id', $channel->tenantId())->findOrFail($channel->id())
            : NotificationChannelModel::query()
                ->where('tenant_id', $channel->tenantId())
                ->where('channel_type', $channel->channelType()->value)
                ->first() ?? new NotificationChannelModel();

        $model->tenant_id = $channel->tenantId();
        $model->channel_type = $channel->channelType()->value;
        $model->config = $channel->config();
        $model->is_active = $channel->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NotificationChannelModel $model): NotificationChannelEntity
    {
        return new NotificationChannelEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            channelType: ChannelType::from($model->channel_type),
            config: $model->config ?? [],
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
