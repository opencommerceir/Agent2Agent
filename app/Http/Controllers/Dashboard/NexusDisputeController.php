<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Contract\Application\Actions\ArbitrateDisputeAction;
use App\Domains\Nexus\Contract\Application\Actions\MoveDisputeToMediationAction;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\ValueObjects\DisputeCaseStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard, never `business.auth`) — the
 * real evidence/mediation/arbitration queue Phase 6/M3 adds on top of the
 * simple Escrow dispute flag (NexusEscrowController, Phase 3/M4, still
 * exists for a direct manual refund outside this richer workflow).
 */
class NexusDisputeController extends Controller
{
    public function __construct(
        private readonly DisputeCaseRepositoryInterface $disputeCases,
        private readonly MoveDisputeToMediationAction $moveToMediation,
        private readonly ArbitrateDisputeAction $arbitrate,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.disputes.index', [
            'open' => $this->disputeCases->findByStatus(DisputeCaseStatus::Open),
            'mediation' => $this->disputeCases->findByStatus(DisputeCaseStatus::Mediation),
        ]);
    }

    public function mediate(int $dispute): RedirectResponse
    {
        $this->moveToMediation->execute($dispute);

        return redirect()->route('dashboard.nexus.disputes.index')->with('status', t('messages.nexus.admin.disputes.moved_to_mediation'));
    }

    public function arbitrate(int $dispute, Request $request): RedirectResponse
    {
        $this->arbitrate->execute($dispute, $request->string('resolution')->toString());

        return redirect()->route('dashboard.nexus.disputes.index')->with('status', t('messages.nexus.admin.disputes.arbitrated'));
    }
}
