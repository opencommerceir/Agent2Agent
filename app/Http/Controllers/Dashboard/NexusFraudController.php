<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Business\Application\Actions\ReactivateBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\ResolveSuspensionAppealAction;
use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionAppealRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessStatus;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAppealStatus;
use App\Domains\Nexus\Reputation\Application\Actions\DetectFraudSignalsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard, never `business.auth`) — Phase
 * 6/M4's fraud/suspension queue: the auto-suspended and manually
 * suspended Businesses side by side (SuspensionRecord's own
 * `triggeredBy` is the only thing that distinguishes them), plus the
 * appeal inbox the roadmap's "auto-suspension with an appeal process"
 * line requires.
 */
class NexusFraudController extends Controller
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly SuspensionAppealRepositoryInterface $appeals,
        private readonly SuspendBusinessAction $suspendBusiness,
        private readonly ReactivateBusinessAction $reactivateBusiness,
        private readonly ResolveSuspensionAppealAction $resolveAppeal,
        private readonly DetectFraudSignalsAction $detectFraudSignals,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.fraud.index', [
            'suspended' => $this->businesses->findByStatus(BusinessStatus::Suspended),
            'pendingAppeals' => $this->appeals->findByStatus(SuspensionAppealStatus::Pending),
        ]);
    }

    public function runDetection(): RedirectResponse
    {
        $suspended = $this->detectFraudSignals->execute();

        return redirect()->route('dashboard.nexus.fraud.index')
            ->with('status', t('messages.nexus.admin.fraud.detection_ran', ['count' => count($suspended)]));
    }

    public function suspend(Request $request): RedirectResponse
    {
        $this->suspendBusiness->execute((int) $request->input('business_id'), $request->string('reason')->toString() ?: 'Manually suspended by admin.');

        return redirect()->route('dashboard.nexus.fraud.index')->with('status', t('messages.nexus.admin.fraud.suspended'));
    }

    public function reactivate(int $business): RedirectResponse
    {
        $this->reactivateBusiness->execute($business, 'Manually reactivated by admin.');

        return redirect()->route('dashboard.nexus.fraud.index')->with('status', t('messages.nexus.admin.fraud.reactivated'));
    }

    public function resolveAppeal(int $appeal, Request $request): RedirectResponse
    {
        $this->resolveAppeal->execute($appeal, $request->boolean('approve'));

        return redirect()->route('dashboard.nexus.fraud.index')->with('status', t('messages.nexus.admin.fraud.appeal_resolved'));
    }
}
