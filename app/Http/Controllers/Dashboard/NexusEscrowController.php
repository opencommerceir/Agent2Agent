<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Contract\Application\Actions\RefundEscrowAction;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\ValueObjects\EscrowStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard, never `business.auth`) —
 * resolves Disputed Escrows (Phase 3/M4). Since Nexus never actually
 * moves real money between Businesses (Escrow's own docblock), this only
 * records the resolution; RefundEscrowAction's own docblock explains why
 * no fund transfer happens.
 */
class NexusEscrowController extends Controller
{
    public function __construct(
        private readonly EscrowRepositoryInterface $escrows,
        private readonly RefundEscrowAction $refundEscrow,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.escrows.index', [
            'disputed' => $this->escrows->findByStatus(EscrowStatus::Disputed),
        ]);
    }

    public function refund(int $escrow): RedirectResponse
    {
        $this->refundEscrow->execute($escrow);

        return redirect()->route('dashboard.nexus.escrows.index')->with('status', t('messages.nexus.admin.escrows.refunded'));
    }
}
