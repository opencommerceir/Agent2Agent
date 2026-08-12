<?php

namespace App\Domains\Nexus\Growth\Interfaces\Http\Controllers;

use App\Domains\Nexus\Growth\Application\Actions\CancelCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\CloseCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\CreateCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\GetCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\JoinCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\LeaveCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\ListOpenCoalitionsAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-facing Group Buying UI — the human-operated counterpart to the
 * nexus.coalition.* MCP capabilities an Agent can also call directly. Same
 * 'business.auth' guard shape every other Growth controller uses.
 */
class CoalitionController extends Controller
{
    public function __construct(
        private readonly ListOpenCoalitionsAction $listOpenCoalitions,
        private readonly GetCoalitionAction $getCoalition,
        private readonly CreateCoalitionAction $createCoalition,
        private readonly JoinCoalitionAction $joinCoalition,
        private readonly LeaveCoalitionAction $leaveCoalition,
        private readonly CloseCoalitionAction $closeCoalition,
        private readonly CancelCoalitionAction $cancelCoalition,
    ) {
    }

    public function index(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::growth.coalitions.index', [
            'coalitions' => $this->listOpenCoalitions->execute($businessId),
        ]);
    }

    public function create(): View
    {
        return view('nexus::growth.coalitions.create', [
            'catalogItemTypes' => CatalogItemType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $validated = $request->validate([
            'target_business_id' => ['required', 'integer'],
            'catalog_item_type' => ['required', 'string'],
            'catalog_item_id' => ['required', 'integer'],
            'unit_price_amount' => ['required', 'integer', 'min:1'],
            'unit_price_currency' => ['required', 'string', 'size:3'],
            'min_participants' => ['required', 'integer', 'min:2'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $coalition = $this->createCoalition->execute(
            organizerBusinessId: $businessId,
            targetBusinessId: (int) $validated['target_business_id'],
            catalogItemType: CatalogItemType::from($validated['catalog_item_type']),
            catalogItemId: (int) $validated['catalog_item_id'],
            unitPriceAmount: (int) $validated['unit_price_amount'],
            unitPriceCurrency: $validated['unit_price_currency'],
            minParticipants: (int) $validated['min_participants'],
            discountPercent: (float) $validated['discount_percent'],
            organizerQuantity: (int) $validated['quantity'],
        );

        return redirect()->route('nexus.growth.coalitions.show', $coalition->id);
    }

    public function show(int $coalition): View
    {
        return view('nexus::growth.coalitions.show', [
            'coalition' => $this->getCoalition->execute($coalition),
            'businessId' => Auth::guard('business')->user()->business_id,
        ]);
    }

    public function join(Request $request, int $coalition): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $this->joinCoalition->execute($coalition, $businessId, (int) $validated['quantity']);

        return redirect()->route('nexus.growth.coalitions.show', $coalition);
    }

    public function leave(int $coalition): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $this->leaveCoalition->execute($coalition, $businessId);

        return redirect()->route('nexus.growth.coalitions.index');
    }

    public function close(int $coalition): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $this->closeCoalition->execute($coalition, $businessId);

        return redirect()->route('nexus.growth.coalitions.show', $coalition);
    }

    public function cancel(int $coalition): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $this->cancelCoalition->execute($coalition, $businessId);

        return redirect()->route('nexus.growth.coalitions.show', $coalition);
    }
}
