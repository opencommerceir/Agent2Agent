<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\Actions\CalculateKPIAction;
use App\Modules\Analytics\Application\Actions\ExportReportAction;
use App\Modules\Analytics\Application\Services\ReportExporter;
use App\Modules\Analytics\Domain\ValueObjects\KPIType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * `/dashboard/analytics` — a filterable single-KPI calculator (date range,
 * tenant, KPI type) plus CSV/PDF export of the 6-KPI summary. Every
 * number comes from `CalculateKPIAction` (the same Action every MCP
 * capability and the Dashboard Home page's own cards use) — no business
 * logic lives in this Controller.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly CalculateKPIAction $calculateKPI,
        private readonly ExportReportAction $exportReport,
        private readonly ReportExporter $exporter,
    ) {
    }

    public function index(Request $request): View
    {
        $tenants = $this->tenants->all();
        $tenantId = $request->integer('tenant_id') ?: (($tenants[0] ?? null)?->id());

        $result = null;

        if ($tenantId !== null && $request->filled('kpi_type') && $request->filled('start_date') && $request->filled('end_date')) {
            $result = $this->calculateKPI->execute(
                $tenantId,
                $request->string('kpi_type')->toString(),
                $request->string('time_period')->toString() ?: 'monthly',
                $request->string('start_date')->toString(),
                $request->string('end_date')->toString(),
            );
        }

        return view('dashboard.analytics.index', [
            'tenants' => $tenants,
            'selectedTenantId' => $tenantId,
            'kpiTypes' => array_map(fn (KPIType $type) => $type->value, KPIType::cases()),
            'result' => $result,
            'startDate' => $request->string('start_date')->toString() ?: now()->startOfMonth()->format('Y-m-d'),
            'endDate' => $request->string('end_date')->toString() ?: now()->format('Y-m-d'),
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        [$headers, $rows] = $this->exportReport->buildKpiSummaryRows(
            (int) $request->integer('tenant_id'),
            $request->string('start_date')->toString(),
            $request->string('end_date')->toString(),
        );

        return response($this->exporter->toCsv($headers, $rows), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="analytics-summary.csv"',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        [$headers, $rows] = $this->exportReport->buildKpiSummaryRows(
            (int) $request->integer('tenant_id'),
            $request->string('start_date')->toString(),
            $request->string('end_date')->toString(),
        );

        return response($this->exporter->toPdf('KPI Summary', $headers, $rows), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="analytics-summary.pdf"',
        ]);
    }
}
