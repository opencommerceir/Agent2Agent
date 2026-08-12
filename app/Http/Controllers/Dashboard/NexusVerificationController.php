<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\VerificationStatus as BusinessVerificationStatus;
use App\Domains\Nexus\Catalog\Application\Actions\RejectProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\RejectServiceAction;
use App\Domains\Nexus\Catalog\Application\Actions\VerifyProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\VerifyServiceAction;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\ListingVerificationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only (core `auth`/`admin` guard, never `business.auth`) — Phase
 * 6/M5's verification queue, spanning all three roadmap-named surfaces
 * ("تأیید هویت کسب‌وکار، تأیید محصولات/خدمات"): pending Businesses (the
 * Phase 1 VerifyBusinessAction never had an admin UI of its own — this is
 * the first one), pending Products, pending Services.
 */
class NexusVerificationController extends Controller
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
        private readonly VerifyBusinessAction $verifyBusiness,
        private readonly VerifyProductAction $verifyProduct,
        private readonly RejectProductAction $rejectProduct,
        private readonly VerifyServiceAction $verifyService,
        private readonly RejectServiceAction $rejectService,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.nexus.verification.index', [
            'pendingBusinesses' => $this->businesses->findByVerificationStatus(BusinessVerificationStatus::Pending),
            'pendingProducts' => $this->products->findByVerificationStatus(ListingVerificationStatus::Pending),
            'pendingServices' => $this->services->findByVerificationStatus(ListingVerificationStatus::Pending),
        ]);
    }

    public function verifyBusiness(int $business): RedirectResponse
    {
        $this->verifyBusiness->execute($business);

        return redirect()->route('dashboard.nexus.verification.index')->with('status', t('messages.nexus.admin.verification.business_verified'));
    }

    public function verifyProduct(int $product): RedirectResponse
    {
        $this->verifyProduct->execute($product);

        return redirect()->route('dashboard.nexus.verification.index')->with('status', t('messages.nexus.admin.verification.listing_verified'));
    }

    public function rejectProduct(int $product): RedirectResponse
    {
        $this->rejectProduct->execute($product);

        return redirect()->route('dashboard.nexus.verification.index')->with('status', t('messages.nexus.admin.verification.listing_rejected'));
    }

    public function verifyService(int $service): RedirectResponse
    {
        $this->verifyService->execute($service);

        return redirect()->route('dashboard.nexus.verification.index')->with('status', t('messages.nexus.admin.verification.listing_verified'));
    }

    public function rejectService(int $service): RedirectResponse
    {
        $this->rejectService->execute($service);

        return redirect()->route('dashboard.nexus.verification.index')->with('status', t('messages.nexus.admin.verification.listing_rejected'));
    }
}
