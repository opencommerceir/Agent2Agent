<?php

namespace App\Domains\Nexus\Analytics\Interfaces\Http\Controllers;

use App\Domains\Nexus\Analytics\Application\Actions\ExportBusinessAnalyticsReportAction;
use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessAnalyticsAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal Analytics page (Phase 8/M1) — same 'business.auth' guard
 * shape every other business-facing controller in this codebase uses. Lives
 * in the Analytics domain's own Interfaces/Http (previously empty — every
 * earlier Analytics dashboard was admin-only, reached from
 * app/Http/Controllers/Dashboard; this is the domain's first business-owner-
 * facing controller).
 */
class BusinessAnalyticsController extends Controller
{
    public function __construct(
        private readonly GetBusinessAnalyticsAction $getBusinessAnalytics,
        private readonly ExportBusinessAnalyticsReportAction $exportBusinessAnalyticsReport,
    ) {
    }

    public function index(): View
    {
        $businessId = $this->actingBusinessId();

        return view('nexus::analytics.index', [
            'analytics' => $this->getBusinessAnalytics->execute($businessId),
        ]);
    }

    public function export(): Response
    {
        $csv = $this->exportBusinessAnalyticsReport->execute($this->actingBusinessId());

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="nexus-business-analytics.csv"',
        ]);
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}
