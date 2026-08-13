<?php

namespace App\Domains\Nexus\PrivateMarketplace\Interfaces\Http\Controllers;

use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\AcceptMemberInvitationAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\AddListingAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\ArchivePrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\CreatePrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\GetPrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\InviteMemberAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\LeaveMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\ListMarketplaceInvitationsForBusinessAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\ListMyPrivateMarketplacesAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\RejectMemberInvitationAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\RemoveListingAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\RemoveMemberAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\SearchPrivateMarketplaceAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PrivateMarketplaceController extends Controller
{
    public function __construct(
        private readonly ListMyPrivateMarketplacesAction $listMine,
        private readonly ListMarketplaceInvitationsForBusinessAction $listInvitations,
        private readonly GetPrivateMarketplaceAction $getMarketplace,
        private readonly SearchPrivateMarketplaceAction $searchMarketplace,
        private readonly CreatePrivateMarketplaceAction $createMarketplace,
        private readonly ArchivePrivateMarketplaceAction $archiveMarketplace,
        private readonly InviteMemberAction $inviteMember,
        private readonly AcceptMemberInvitationAction $acceptInvitation,
        private readonly RejectMemberInvitationAction $rejectInvitation,
        private readonly RemoveMemberAction $removeMember,
        private readonly LeaveMarketplaceAction $leaveMarketplace,
        private readonly AddListingAction $addListing,
        private readonly RemoveListingAction $removeListing,
    ) {
    }

    public function index(): View
    {
        $businessId = Auth::guard('business')->user()->business_id;

        return view('nexus::private-marketplace.index', [
            'marketplaces' => $this->listMine->execute($businessId),
            'invitations' => $this->listInvitations->execute($businessId),
        ]);
    }

    public function create(): View
    {
        return view('nexus::private-marketplace.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $validated = $request->validate([
            'name_fa' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'branding_primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $marketplace = $this->createMarketplace->execute(
            $businessId,
            $validated['name_fa'],
            $validated['name_en'],
            $validated['branding_primary_color'] ?: null,
        );

        return redirect()->route('nexus.private-marketplace.show', $marketplace->id);
    }

    public function show(int $marketplace): View
    {
        $businessId = Auth::guard('business')->user()->business_id;
        $data = $this->getMarketplace->execute($marketplace);

        $isOwner = $data->ownerBusinessId === $businessId;
        $isMember = $isOwner || collect($data->members)->contains(fn ($m) => $m['businessId'] === $businessId && $m['status'] === 'active');

        if (! $isMember) {
            abort(403);
        }

        $listings = $this->searchMarketplace->execute($marketplace, $businessId);

        return view('nexus::private-marketplace.show', [
            'marketplace' => $data,
            'listings' => $listings['listings'],
            'businessId' => $businessId,
            'isOwner' => $isOwner,
            'catalogItemTypes' => CatalogItemType::cases(),
        ]);
    }

    public function archive(int $marketplace): RedirectResponse
    {
        $this->archiveMarketplace->execute($marketplace, Auth::guard('business')->user()->business_id);

        return redirect()->route('nexus.private-marketplace.index');
    }

    public function invite(Request $request, int $marketplace): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;
        $validated = $request->validate(['target_business_id' => ['required', 'integer']]);

        $this->inviteMember->execute($marketplace, $businessId, (int) $validated['target_business_id']);

        return redirect()->route('nexus.private-marketplace.show', $marketplace);
    }

    public function accept(int $member): RedirectResponse
    {
        $this->acceptInvitation->execute($member, Auth::guard('business')->user()->business_id);

        return redirect()->route('nexus.private-marketplace.index');
    }

    public function reject(int $member): RedirectResponse
    {
        $this->rejectInvitation->execute($member, Auth::guard('business')->user()->business_id);

        return redirect()->route('nexus.private-marketplace.index');
    }

    public function removeMember(int $marketplace, int $member): RedirectResponse
    {
        $this->removeMember->execute($member, Auth::guard('business')->user()->business_id);

        return redirect()->route('nexus.private-marketplace.show', $marketplace);
    }

    public function leave(int $member): RedirectResponse
    {
        $this->leaveMarketplace->execute($member, Auth::guard('business')->user()->business_id);

        return redirect()->route('nexus.private-marketplace.index');
    }

    public function addListing(Request $request, int $marketplace): RedirectResponse
    {
        $businessId = Auth::guard('business')->user()->business_id;

        $validated = $request->validate([
            'catalog_item_type' => ['required', 'string'],
            'catalog_item_id' => ['required', 'integer'],
            'special_price_amount' => ['required', 'integer', 'min:1'],
            'special_price_currency' => ['required', 'string', 'size:3'],
        ]);

        $this->addListing->execute(
            $marketplace,
            $businessId,
            CatalogItemType::from($validated['catalog_item_type']),
            (int) $validated['catalog_item_id'],
            (int) $validated['special_price_amount'],
            $validated['special_price_currency'],
        );

        return redirect()->route('nexus.private-marketplace.show', $marketplace);
    }

    public function removeListing(int $marketplace, int $listing): RedirectResponse
    {
        $this->removeListing->execute($listing, Auth::guard('business')->user()->business_id);

        return redirect()->route('nexus.private-marketplace.show', $marketplace);
    }
}
