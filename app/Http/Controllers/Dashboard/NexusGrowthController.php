<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Analytics\Application\Actions\GetGrowthDashboardAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard) — the roadmap's Viral Analytics
 * dashboard (Phase 5/M5). Same shape NexusRevenueController (Phase 3/M6)
 * already established.
 */
class NexusGrowthController extends Controller
{
    public function __construct(
        private readonly GetGrowthDashboardAction $getGrowthDashboard,
    ) {
    }

    public function index(Request $request): View
    {
        $from = $request->date('from');
        $to = $request->date('to');

        return view('dashboard.nexus.growth.index', [
            'growth' => $this->getGrowthDashboard->execute($from, $to)->toArray(),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
    }
}
