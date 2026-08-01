<?php

namespace App\Modules\Analytics\Application\Actions;

use App\Modules\Analytics\Application\Services\ReportExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The MCP-facing export path — `analytics.report.export`'s own handler.
 * Unlike a Dashboard browser request (which can stream bytes straight
 * back as the HTTP response, see `AnalyticsController::exportCsv()`/
 * `exportPdf()`), an MCP capability's response body is JSON — there is no
 * way to return raw file bytes as `{"file_url": "..."}`. This Action
 * writes the exported file to the `public` disk under
 * `analytics-exports/` and returns its public URL instead.
 *
 * `report_type` only supports `kpi_summary` this stage — the 6 headline
 * KPIs (the same ones the Dashboard Home page's own cards show) for the
 * given date range. Exporting one of Reporting's own 5 report types
 * instead is real future work (§9), not built here — this stage's own
 * request didn't specify which report(s) `analytics.report.export` should
 * cover beyond "a report", so the one genuinely new, already-tested
 * report this module has (its own KPI summary) is what's implemented.
 */
final class ExportReportAction
{
    private const KPI_TYPES = [
        'revenue' => 'Revenue',
        'total_orders' => 'Total Orders',
        'average_order_value' => 'Average Order Value',
        'total_customers' => 'Total Customers',
        'conversion_rate' => 'Conversion Rate (%)',
        'active_loyalty_accounts' => 'Active Loyalty Accounts',
    ];

    public function __construct(
        private readonly CalculateKPIAction $calculateKPI,
        private readonly ReportExporter $exporter,
    ) {
    }

    public function execute(int $tenantId, string $reportType, string $format, string $startDate, string $endDate): string
    {
        [$headers, $rows] = $this->buildKpiSummaryRows($tenantId, $startDate, $endDate);
        $filename = sprintf('analytics-exports/%s-%s.%s', $reportType, Str::uuid(), $format);

        $contents = $format === 'pdf'
            ? $this->exporter->toPdf('KPI Summary', $headers, $rows)
            : $this->exporter->toCsv($headers, $rows);

        Storage::disk('public')->put($filename, $contents);

        return Storage::disk('public')->url($filename);
    }

    /**
     * The row-building half, split out so the Dashboard's own
     * `AnalyticsController::exportCsv()`/`exportPdf()` can stream the
     * exact same content straight back as a download response — a
     * browser request already holds the connection open, so it has no
     * need for the disk-write-then-URL round trip `execute()` needs for
     * MCP's own JSON response body.
     *
     * @return array{0: list<string>, 1: list<array{0: string, 1: string}>}
     */
    public function buildKpiSummaryRows(int $tenantId, string $startDate, string $endDate): array
    {
        $rows = [];

        foreach (self::KPI_TYPES as $kpiType => $label) {
            $value = $this->calculateKPI->execute($tenantId, $kpiType, 'daily', $startDate, $endDate);
            $rows[] = [$label, $this->formatAmount($value->amount, $value->unit)];
        }

        return [['KPI', 'Value'], $rows];
    }

    private function formatAmount(int $amount, string $unit): string
    {
        return match ($unit) {
            'PCT' => number_format($amount / 100, 2).'%',
            'CNT', 'PTS' => (string) $amount,
            default => number_format($amount / 100, 2).' '.$unit,
        };
    }
}
