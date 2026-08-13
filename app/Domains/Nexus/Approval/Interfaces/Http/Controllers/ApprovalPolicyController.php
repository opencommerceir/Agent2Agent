<?php

namespace App\Domains\Nexus\Approval\Interfaces\Http\Controllers;

use App\Domains\Nexus\Approval\Application\Actions\GetApprovalPolicyAction;
use App\Domains\Nexus\Approval\Application\Actions\SetApprovalPolicyAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Owner-only settings page for Phase 7/M4's multi-level Approval Workflow —
 * "Agent -> Manager -> CFO, configurable by deal volume" from the roadmap.
 * A Business that never visits this page keeps exactly Phase 2's original
 * single-implicit-human pending_approval behavior (SetApprovalPolicyAction
 * is never called, so ApprovalPolicyRepositoryInterface::findByBusinessId()
 * stays null).
 */
class ApprovalPolicyController extends Controller
{
    public function __construct(
        private readonly GetApprovalPolicyAction $getApprovalPolicy,
        private readonly SetApprovalPolicyAction $setApprovalPolicy,
    ) {
    }

    public function edit(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::business.approval-policy.edit', [
            'policy' => $this->getApprovalPolicy->execute($businessId),
            'roles' => TeamMemberRole::cases(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;
        $callingOwnerId = Auth::guard('business')->id();

        // Blank ("unused") rows from the fixed-length form grid are
        // dropped before validation — the form always renders one spare
        // row beyond the current policy so an Owner can grow the chain,
        // and that spare row has no role selected by default.
        $submittedLevels = array_values(array_filter(
            $request->array('levels'),
            fn (array $level) => ($level['role'] ?? '') !== '',
        ));

        $validated = validator(['levels' => $submittedLevels], [
            'levels' => ['required', 'array', 'min:1'],
            'levels.*.role' => ['required', 'string'],
            'levels.*.min_amount' => ['required', 'integer', 'min:0'],
        ])->validate();

        $levels = array_map(fn (array $level) => [
            'role' => $level['role'],
            'minAmount' => (int) $level['min_amount'],
        ], $validated['levels']);

        $this->setApprovalPolicy->execute($businessId, $callingOwnerId, $levels);

        return redirect()->route('nexus.business.approval-policy.edit')->with('status', t('messages.nexus.business.approval_policy.saved'));
    }
}
