<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Application\Actions\ListMyActiveSessionsAction;
use App\Domains\Nexus\Business\Application\Actions\RevokeSessionAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BusinessSessionController extends Controller
{
    public function __construct(
        private readonly ListMyActiveSessionsAction $listSessions,
        private readonly RevokeSessionAction $revokeSession,
    ) {
    }

    public function index(Request $request): View
    {
        $ownerId = Auth::guard('business')->id();

        return view('nexus::business.sessions.index', [
            'sessions' => $this->listSessions->execute($ownerId, $request->session()->getId()),
        ]);
    }

    public function destroy(Request $request, string $session): RedirectResponse
    {
        $this->revokeSession->execute($session, Auth::guard('business')->id());

        return redirect()->route('nexus.business.sessions.index');
    }
}
