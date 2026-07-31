<?php

namespace App\Modules\Reporting\Infrastructure\Repositories;

use App\Modules\Reporting\Domain\Entities\Report as ReportEntity;
use App\Modules\Reporting\Domain\Entities\ReportResult as ReportResultEntity;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;
use App\Modules\Reporting\Infrastructure\Models\Report as ReportModel;
use App\Modules\Reporting\Infrastructure\Models\ReportResult as ReportResultModel;
use DateTimeImmutable;

class EloquentReportRepository implements ReportRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?ReportEntity
    {
        $model = ReportModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function list(int $tenantId, ?ReportType $reportType, int $limit): array
    {
        $builder = ReportModel::query()->where('tenant_id', $tenantId);

        if ($reportType !== null) {
            $builder->where('report_type', $reportType->value);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (ReportModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ReportEntity $report): ReportEntity
    {
        $model = new ReportModel();
        $model->tenant_id = $report->tenantId();
        $model->name = $report->name();
        $model->report_type = $report->reportType()->value;
        $model->date_range_start = $report->dateRange()->startDate();
        $model->date_range_end = $report->dateRange()->endDate();
        $model->filters = $report->filters()->toArray();
        $model->created_by = $report->createdBy();
        $model->save();

        return $this->toEntity($model);
    }

    public function saveResult(ReportResultEntity $result): ReportResultEntity
    {
        $model = new ReportResultModel();
        $model->report_id = $result->reportId();
        $model->tenant_id = $result->tenantId();
        $model->result_data = $result->resultData();
        $model->generated_at = $result->generatedAt();
        $model->expires_at = $result->expiresAt();
        $model->save();

        return $this->toResultEntity($model);
    }

    public function findLatestResult(int $reportId, int $tenantId): ?ReportResultEntity
    {
        $model = ReportResultModel::query()
            ->where('tenant_id', $tenantId)
            ->where('report_id', $reportId)
            ->orderBy('id', 'desc')
            ->first();

        return $model ? $this->toResultEntity($model) : null;
    }

    private function toEntity(ReportModel $model): ReportEntity
    {
        return new ReportEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            reportType: ReportType::from($model->report_type),
            dateRange: DateRange::fromStrings(
                $model->date_range_start->format('Y-m-d'),
                $model->date_range_end->format('Y-m-d'),
            ),
            filters: ReportFilter::fromArray($model->filters ?? []),
            createdBy: $model->created_by,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    private function toResultEntity(ReportResultModel $model): ReportResultEntity
    {
        return new ReportResultEntity(
            id: $model->id,
            reportId: $model->report_id,
            tenantId: $model->tenant_id,
            resultData: $model->result_data ?? [],
            generatedAt: DateTimeImmutable::createFromInterface($model->generated_at),
            expiresAt: $model->expires_at ? DateTimeImmutable::createFromInterface($model->expires_at) : null,
        );
    }
}
