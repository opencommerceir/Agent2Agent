<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Audit\Application\Actions\VerifyAuditChainIntegrityAction;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard, never `business.auth` — same
 * boundary every other Nexus admin controller since Phase 1/M1 draws) —
 * Phase 7/M9's compliance surface: the most recent entries in the
 * hash-chained audit trail, and a manual "verify chain integrity" action
 * a compliance officer (or SOC 2 / ISO 27001 auditor) can run on demand.
 */
class NexusAuditController extends Controller
{
    public function __construct(
        private readonly AuditLogEntryRepositoryInterface $entries,
        private readonly VerifyAuditChainIntegrityAction $verifyChainIntegrity,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.audit.index', [
            'entries' => $this->entries->latest(100),
            'totalCount' => $this->entries->count(),
        ]);
    }

    public function verify(): RedirectResponse
    {
        $result = $this->verifyChainIntegrity->execute();

        $status = $result['intact']
            ? t('messages.nexus.admin.audit.verify_intact', ['count' => $result['checkedCount']])
            : t('messages.nexus.admin.audit.verify_broken', ['sequence' => $result['brokenAtSequence']]);

        return redirect()->route('dashboard.nexus.audit.index')->with('status', $status);
    }
}
