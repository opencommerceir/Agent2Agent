<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Analytics\Application\Actions\GetPlatformOverviewAction;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard) — the real admin home page.
 * DashboardController::index() redirects here instead of rendering
 * anything itself, the same "thin redirect, real page lives under
 * dashboard.nexus.*" shape it already had pointing at Revenue — except
 * this page is actually built to be a landing page (a "what needs my
 * attention today" summary), not a page that happened to be convenient.
 */
class NexusOverviewController extends Controller
{
    public function __construct(
        private readonly GetPlatformOverviewAction $getPlatformOverview,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.overview.index', [
            'overview' => $this->getPlatformOverview->execute(),
        ]);
    }
}
