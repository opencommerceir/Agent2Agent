<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookSubscription as SubscriptionEntity;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use App\Domains\Nexus\Developer\Infrastructure\Models\WebhookSubscription as SubscriptionModel;
use DateTimeImmutable;

class EloquentWebhookSubscriptionRepository implements WebhookSubscriptionRepositoryInterface
{
    public function findById(int $id): ?SubscriptionEntity
    {
        $model = SubscriptionModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByBusinessId(int $businessId): array
    {
        return SubscriptionModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SubscriptionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findActiveByBusinessIdAndEvent(int $businessId, WebhookEvent $event): array
    {
        return SubscriptionModel::query()
            ->where('business_id', $businessId)
            ->whereNull('revoked_at')
            ->get()
            ->map(fn (SubscriptionModel $model) => $this->toEntity($model))
            ->filter(fn (SubscriptionEntity $subscription) => $subscription->isSubscribedTo($event))
            ->values()
            ->all();
    }

    public function save(SubscriptionEntity $subscription): SubscriptionEntity
    {
        $model = $subscription->id()
            ? SubscriptionModel::query()->findOrFail($subscription->id())
            : new SubscriptionModel();

        $model->business_id = $subscription->businessId();
        $model->url = $subscription->url();
        $model->secret = $subscription->secret();
        $model->events = array_map(fn (WebhookEvent $event) => $event->value, $subscription->events());
        $model->revoked_at = $subscription->revokedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SubscriptionModel $model): SubscriptionEntity
    {
        return new SubscriptionEntity(
            id: $model->id,
            businessId: $model->business_id,
            url: $model->url,
            secret: $model->secret,
            events: array_map(fn (string $value) => WebhookEvent::from($value), $model->events ?? []),
            revokedAt: $model->revoked_at ? DateTimeImmutable::createFromInterface($model->revoked_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
