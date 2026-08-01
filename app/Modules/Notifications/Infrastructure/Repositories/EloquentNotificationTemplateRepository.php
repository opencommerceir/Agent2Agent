<?php

namespace App\Modules\Notifications\Infrastructure\Repositories;

use App\Core\Domain\ValueObjects\Language;
use App\Modules\Notifications\Domain\Entities\NotificationTemplate as NotificationTemplateEntity;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Infrastructure\Models\NotificationTemplate as NotificationTemplateModel;
use DateTimeImmutable;

class EloquentNotificationTemplateRepository implements NotificationTemplateRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?NotificationTemplateEntity
    {
        $model = NotificationTemplateModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findActive(
        int $tenantId,
        NotificationType $type,
        ChannelType $channelType,
        Language $language = Language::English,
    ): ?NotificationTemplateEntity {
        $model = $this->activeQuery($tenantId, $type, $channelType, $language)->first();

        if ($model === null && $language !== Language::English) {
            $model = $this->activeQuery($tenantId, $type, $channelType, Language::English)->first();
        }

        return $model ? $this->toEntity($model) : null;
    }

    private function activeQuery(int $tenantId, NotificationType $type, ChannelType $channelType, Language $language)
    {
        return NotificationTemplateModel::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type->value)
            ->where('channel_type', $channelType->value)
            ->where('language', $language->value)
            ->where('is_active', true);
    }

    public function list(int $tenantId, ?NotificationType $type, ?ChannelType $channelType): array
    {
        $builder = NotificationTemplateModel::query()->where('tenant_id', $tenantId);

        if ($type !== null) {
            $builder->where('type', $type->value);
        }

        if ($channelType !== null) {
            $builder->where('channel_type', $channelType->value);
        }

        return $builder->orderBy('id', 'desc')
            ->get()
            ->map(fn (NotificationTemplateModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(NotificationTemplateEntity $template): NotificationTemplateEntity
    {
        $model = $template->id()
            ? NotificationTemplateModel::query()->where('tenant_id', $template->tenantId())->findOrFail($template->id())
            : new NotificationTemplateModel();

        $model->tenant_id = $template->tenantId();
        $model->type = $template->type()->value;
        $model->channel_type = $template->channelType()->value;
        $model->subject_template = $template->subjectTemplate();
        $model->body_template = $template->bodyTemplate();
        $model->variables = $template->variables();
        $model->is_active = $template->isActive();
        $model->language = $template->language()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NotificationTemplateModel $model): NotificationTemplateEntity
    {
        return new NotificationTemplateEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            type: NotificationType::from($model->type),
            channelType: ChannelType::from($model->channel_type),
            subjectTemplate: $model->subject_template,
            bodyTemplate: $model->body_template,
            variables: $model->variables ?? [],
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            language: Language::from($model->language),
        );
    }
}
