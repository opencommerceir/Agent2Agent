<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Placeholder for M2 (just proves the 'business.auth' guard works
 * end-to-end); M6 replaces the view with the full Jarvis dashboard
 * (Agent status, catalog summary, credit/negotiation placeholders).
 */
class BusinessDashboardController extends Controller
{
    public function index(): View
    {
        return view('nexus::business.dashboard', ['owner' => Auth::guard('business')->user()]);
    }
}
