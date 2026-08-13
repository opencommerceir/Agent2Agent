<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookDeliveryLog as LogEntity;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookDeliveryLogRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use App\Domains\Nexus\Developer\Infrastructure\Models\WebhookDeliveryLog as LogModel;
use DateTimeImmutable;

class EloquentWebhookDeliveryLogRepository implements WebhookDeliveryLogRepositoryInterface
{
    public function findByBusinessId(int $businessId): array
    {
        return LogModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LogModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(LogEntity $log): LogEntity
    {
        $model = new LogModel();
        $model->business_id = $log->businessId();
        $model->subscription_id = $log->subscriptionId();
        $model->event = $log->event()->value;
        $model->url = $log->url();
        $model->succeeded = $log->succeeded();
        $model->http_status = $log->httpStatus();
        $model->error_message = $log->errorMessage();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(LogModel $model): LogEntity
    {
        return new LogEntity(
            id: $model->id,
            businessId: $model->business_id,
            subscriptionId: $model->subscription_id,
            event: WebhookEvent::from($model->event),
            url: $model->url,
            succeeded: $model->succeeded,
            httpStatus: $model->http_status,
            errorMessage: $model->error_message,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
