<?php

namespace App\Modules\Notifications\Infrastructure\Repositories;

use App\Modules\Notifications\Domain\Entities\NotificationPreference as NotificationPreferenceEntity;
use App\Modules\Notifications\Domain\Repositories\NotificationPreferenceRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;
use App\Modules\Notifications\Infrastructure\Models\NotificationPreference as NotificationPreferenceModel;
use DateTimeImmutable;

class EloquentNotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    public function find(
        int $tenantId,
        RecipientType $recipientType,
        int $recipientId,
        NotificationType $notificationType,
        ChannelType $channelType,
    ): ?NotificationPreferenceEntity {
        $model = NotificationPreferenceModel::query()
            ->where('tenant_id', $tenantId)
            ->where('recipient_type', $recipientType->value)
            ->where('recipient_id', $recipientId)
            ->where('notification_type', $notificationType->value)
            ->where('channel_type', $channelType->value)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(NotificationPreferenceEntity $preference): NotificationPreferenceEntity
    {
        $model = $preference->id()
            ? NotificationPreferenceModel::query()->where('tenant_id', $preference->tenantId())->findOrFail($preference->id())
            : NotificationPreferenceModel::query()
                ->where('tenant_id', $preference->tenantId())
                ->where('recipient_type', $preference->recipientType()->value)
                ->where('recipient_id', $preference->recipientId())
                ->where('notification_type', $preference->notificationType()->value)
                ->where('channel_type', $preference->channelType()->value)
                ->first() ?? new NotificationPreferenceModel();

        $model->tenant_id = $preference->tenantId();
        $model->recipient_type = $preference->recipientType()->value;
        $model->recipient_id = $preference->recipientId();
        $model->notification_type = $preference->notificationType()->value;
        $model->channel_type = $preference->channelType()->value;
        $model->is_enabled = $preference->isEnabled();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NotificationPreferenceModel $model): NotificationPreferenceEntity
    {
        return new NotificationPreferenceEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            recipientType: RecipientType::from($model->recipient_type),
            recipientId: $model->recipient_id,
            notificationType: NotificationType::from($model->notification_type),
            channelType: ChannelType::from($model->channel_type),
            isEnabled: $model->is_enabled,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
