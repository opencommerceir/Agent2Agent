<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\AgentTemplateInstall as InstallEntity;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentTemplateInstallRepositoryInterface;
use App\Domains\Nexus\Developer\Infrastructure\Models\AgentTemplateInstall as InstallModel;
use DateTimeImmutable;

class EloquentAgentTemplateInstallRepository implements AgentTemplateInstallRepositoryInterface
{
    public function findByInstallingBusinessId(int $businessId): array
    {
        return InstallModel::query()
            ->where('installing_business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InstallModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(InstallEntity $install): InstallEntity
    {
        $model = new InstallModel();
        $model->template_id = $install->templateId();
        $model->installing_business_id = $install->installingBusinessId();
        $model->publisher_business_id = $install->publisherBusinessId();
        $model->price_credits = $install->priceCredits();
        $model->platform_fee_credits = $install->platformFeeCredits();
        $model->publisher_earnings_credits = $install->publisherEarningsCredits();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(InstallModel $model): InstallEntity
    {
        return new InstallEntity(
            id: $model->id,
            templateId: $model->template_id,
            installingBusinessId: $model->installing_business_id,
            publisherBusinessId: $model->publisher_business_id,
            priceCredits: $model->price_credits,
            platformFeeCredits: $model->platform_fee_credits,
            publisherEarningsCredits: $model->publisher_earnings_credits,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
