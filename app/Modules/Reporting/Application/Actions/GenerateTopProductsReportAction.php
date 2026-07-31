<?php

namespace App\Modules\Reporting\Application\Actions;

use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Reporting\Application\DTOs\TopProductsReportData;
use App\Modules\Reporting\Domain\Entities\Report;
use App\Modules\Reporting\Domain\Entities\ReportResult;
use App\Modules\Reporting\Domain\Repositories\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\Services\TopProductsReportGenerator;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use App\Modules\Reporting\Domain\ValueObjects\ReportFilter;
use App\Modules\Reporting\Domain\ValueObjects\ReportType;
use App\Modules\Reporting\Infrastructure\Queries\TopProductsQueryBuilder;

/**
 * `TopProductsQueryBuilder::top()` already ranks and limits in SQL
 * (Query Optimization rule). Resolving each returned row's Product name
 * is a bounded (≤ `limit`, default 10) per-id lookup via Commerce's own
 * `ProductRepositoryInterface` — the same "small, bounded per-item
 * Repository lookup, not a batch method added to another module's
 * Interface" precedent Finance's `CreateInvoiceAction` already
 * established for resolving an OrderItem's Product name.
 */
final class GenerateTopProductsReportAction
{
    private const DEFAULT_LIMIT = 10;

    public function __construct(
        private readonly TopProductsQueryBuilder $query,
        private readonly TopProductsReportGenerator $generator,
        private readonly ProductRepositoryInterface $products,
        private readonly ReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @return array{report: array<string, mixed>}
     */
    public function execute(int $tenantId, int $agentId, string $startDate, string $endDate, ?int $limit = null): array
    {
        $limit ??= self::DEFAULT_LIMIT;
        $dateRange = DateRange::fromStrings($startDate, $endDate);

        $rows = $this->query->top($tenantId, $dateRange, $limit);

        $names = [];

        foreach ($rows as $row) {
            $product = $this->products->findById($row['product_id'], $tenantId);
            $names[$row['product_id']] = $product?->name() ?? "Product #{$row['product_id']}";
        }

        $data = TopProductsReportData::fromArray($this->generator->generate($rows, $names));

        $report = Report::create(
            $tenantId,
            'Top Products Report',
            ReportType::TopProducts,
            $dateRange,
            ReportFilter::fromArray(['limit' => $limit]),
            $agentId,
        );
        $report = $this->reports->save($report);
        $this->reports->saveResult(ReportResult::generate($report->id(), $tenantId, $data->toArray()));

        return ['report' => $data->toArray()];
    }
}
