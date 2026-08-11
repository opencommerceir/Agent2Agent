<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessLogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('business')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('nexus.business.login');
    }
}
