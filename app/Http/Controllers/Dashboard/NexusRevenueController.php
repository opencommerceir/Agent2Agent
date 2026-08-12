<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Analytics\Application\Actions\GetRevenueDashboardAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard) — the roadmap's Revenue
 * Dashboard (Phase 3/M6).
 */
class NexusRevenueController extends Controller
{
    public function __construct(
        private readonly GetRevenueDashboardAction $getRevenueDashboard,
    ) {
    }

    public function index(Request $request): View
    {
        $from = $request->date('from');
        $to = $request->date('to');

        return view('dashboard.nexus.revenue.index', [
            'revenue' => $this->getRevenueDashboard->execute($from, $to),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
    }
}
