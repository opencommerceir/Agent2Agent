<?php

namespace App\Modules\Notifications\Infrastructure\Repositories;

use App\Modules\Notifications\Domain\Entities\Notification as NotificationEntity;
use App\Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\DeliveryStatus;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Infrastructure\Models\Notification as NotificationModel;
use DateTimeImmutable;

class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?NotificationEntity
    {
        $model = NotificationModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function list(int $tenantId, ?NotificationType $type, ?DeliveryStatus $status, int $limit): array
    {
        $builder = NotificationModel::query()->where('tenant_id', $tenantId);

        if ($type !== null) {
            $builder->where('type', $type->value);
        }

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(NotificationEntity $notification): NotificationEntity
    {
        $model = $notification->id()
            ? NotificationModel::query()->where('tenant_id', $notification->tenantId())->findOrFail($notification->id())
            : new NotificationModel();

        $model->tenant_id = $notification->tenantId();
        $model->type = $notification->type()->value;
        $model->channel_type = $notification->channelType()->value;
        $model->recipient = $notification->recipient()->value();
        $model->subject = $notification->subject();
        $model->body = $notification->body();
        $model->template_id = $notification->templateId();
        $model->status = $notification->status()->value;
        $model->sent_at = $notification->sentAt();
        $model->delivered_at = $notification->deliveredAt();
        $model->failed_at = $notification->failedAt();
        $model->error_message = $notification->errorMessage();
        $model->metadata = $notification->metadata();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NotificationModel $model): NotificationEntity
    {
        return new NotificationEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            type: NotificationType::from($model->type),
            channelType: ChannelType::from($model->channel_type),
            recipient: new Recipient($model->recipient),
            subject: $model->subject,
            body: $model->body,
            templateId: $model->template_id,
            status: DeliveryStatus::from($model->status),
            sentAt: $model->sent_at ? DateTimeImmutable::createFromInterface($model->sent_at) : null,
            deliveredAt: $model->delivered_at ? DateTimeImmutable::createFromInterface($model->delivered_at) : null,
            failedAt: $model->failed_at ? DateTimeImmutable::createFromInterface($model->failed_at) : null,
            errorMessage: $model->error_message,
            metadata: $model->metadata ?? [],
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
