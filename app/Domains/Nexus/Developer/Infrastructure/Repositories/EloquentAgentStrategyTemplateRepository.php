<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\AgentStrategyTemplate as TemplateEntity;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentStrategyTemplateRepositoryInterface;
use App\Domains\Nexus\Developer\Infrastructure\Models\AgentStrategyTemplate as TemplateModel;
use DateTimeImmutable;

class EloquentAgentStrategyTemplateRepository implements AgentStrategyTemplateRepositoryInterface
{
    public function findById(int $id): ?TemplateEntity
    {
        $model = TemplateModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findActive(?string $query): array
    {
        return TemplateModel::query()
            ->whereNull('revoked_at')
            ->when($query, fn ($builder) => $builder->where(fn ($inner) => $inner
                ->where('name_fa', 'like', "%{$query}%")
                ->orWhere('name_en', 'like', "%{$query}%")))
            ->orderByDesc('install_count')
            ->get()
            ->map(fn (TemplateModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByPublisherBusinessId(int $businessId): array
    {
        return TemplateModel::query()
            ->where('publisher_business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TemplateModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(TemplateEntity $template): TemplateEntity
    {
        $model = $template->id()
            ? TemplateModel::query()->findOrFail($template->id())
            : new TemplateModel();

        $model->publisher_business_id = $template->publisherBusinessId();
        $model->name_fa = $template->nameFa();
        $model->name_en = $template->nameEn();
        $model->description_fa = $template->descriptionFa();
        $model->description_en = $template->descriptionEn();
        $model->personality = $template->personality();
        $model->tone = $template->tone();
        $model->strategies = $template->strategies();
        $model->price_credits = $template->priceCredits();
        $model->install_count = $template->installCount();
        $model->revoked_at = $template->revokedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(TemplateModel $model): TemplateEntity
    {
        return new TemplateEntity(
            id: $model->id,
            publisherBusinessId: $model->publisher_business_id,
            nameFa: $model->name_fa,
            nameEn: $model->name_en,
            descriptionFa: $model->description_fa,
            descriptionEn: $model->description_en,
            personality: $model->personality,
            tone: $model->tone,
            strategies: $model->strategies ?? [],
            priceCredits: $model->price_credits,
            installCount: $model->install_count,
            revokedAt: $model->revoked_at ? DateTimeImmutable::createFromInterface($model->revoked_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
