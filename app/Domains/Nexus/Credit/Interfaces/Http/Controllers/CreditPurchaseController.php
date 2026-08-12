<?php

namespace App\Domains\Nexus\Credit\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Credit\Application\Actions\PurchaseCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The business-portal package picker + purchase initiator
 * (`nexus.credit.purchase.*`, `business.auth` guarded). Thin — every real
 * decision (which gateway, currency conversion, session bookkeeping) lives
 * in PurchaseCreditsAction.
 */
class CreditPurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseCreditsAction $purchaseCredits,
    ) {
    }

    public function index(): View
    {
        return view('nexus::business.credit-purchase', ['packages' => CreditPackage::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $package = CreditPackage::from($request->string('package')->toString());

        $result = $this->purchaseCredits->execute($this->actingBusinessId(), $package);

        return redirect()->away($result['redirect_url']);
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}
