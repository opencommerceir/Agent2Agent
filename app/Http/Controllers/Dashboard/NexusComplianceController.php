<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Analytics\Application\Actions\GetComplianceOverviewAction;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard) — the roadmap's "SOC 2 / ISO
 * 27001 آمادگی" line read as a self-assessment overview (Phase 7/M10),
 * same boundary every other Nexus admin controller draws.
 */
class NexusComplianceController extends Controller
{
    public function __construct(
        private readonly GetComplianceOverviewAction $getComplianceOverview,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.compliance.index', [
            'overview' => $this->getComplianceOverview->execute(),
        ]);
    }
}
