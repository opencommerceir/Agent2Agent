<?php

use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessDashboardController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessLoginController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessLogoutController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\RegisterBusinessController;
use App\Domains\Nexus\Credit\Interfaces\Http\Controllers\CreditPurchaseCallbackController;
use App\Domains\Nexus\Credit\Interfaces\Http\Controllers\CreditPurchaseController;
use App\Domains\Nexus\Growth\Interfaces\Http\Controllers\CoalitionController;
use App\Domains\Nexus\Growth\Interfaces\Http\Controllers\InviteController;
use App\Domains\Nexus\Growth\Interfaces\Http\Controllers\ReferralController;
use App\Domains\Nexus\Holding\Interfaces\Http\Controllers\HoldingController;
use App\Domains\Nexus\Marketplace\Interfaces\Http\Controllers\NetworkController;
use App\Domains\Nexus\Negotiation\Interfaces\Http\Controllers\NegotiationViewerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nexus Web Routes
|--------------------------------------------------------------------------
|
| Loaded by NexusServiceProvider::boot() via loadRoutesFrom() — the same
| "a module owns and loads its own routes" shape routes/mcp.php and
| routes/agents.php already use, independent of bootstrap/app.php's web/api
| split. Unlike those two (stateless JSON for AI Agents), these are
| browser-facing pages, so they're wrapped in Laravel's own 'web'
| middleware group explicitly (session/cookies/CSRF) — loadRoutesFrom()
| does not apply it automatically the way passing a file as `web:` to
| withRouting() would.
|
*/

Route::middleware('web')->group(function () {
    Route::get('/nexus', fn () => view('nexus::welcome'))->name('nexus.welcome');

    // Business portal (Phase 1, M2) — its own 'business' guard, fully
    // independent of the admin Dashboard's 'auth'/'guest'/'admin'.
    Route::prefix('nexus/business')->name('nexus.business.')->group(function () {
        Route::middleware('business.guest:business')->group(function () {
            Route::get('/register', [RegisterBusinessController::class, 'create'])->name('register');
            Route::post('/register', [RegisterBusinessController::class, 'store'])->name('register.store');
            Route::get('/login', [BusinessLoginController::class, 'create'])->name('login');
            Route::post('/login', [BusinessLoginController::class, 'store'])->name('login.store');
        });

        Route::middleware('business.auth:business')->group(function () {
            Route::post('/logout', BusinessLogoutController::class)->name('logout');
            Route::get('/dashboard', [BusinessDashboardController::class, 'index'])->name('dashboard');
            Route::post('/dashboard/appeal', [BusinessDashboardController::class, 'submitSuspensionAppeal'])->name('dashboard.appeal');
        });
    });

    // Credit purchase (Phase 3, M3) — package picker is 'business.auth'
    // guarded (only a logged-in Business owner spends real money), but the
    // gateway callback below is public (an external gateway's own browser
    // redirect, same as Commerce's own PaymentCallbackController).
    Route::prefix('nexus/credit')->name('nexus.credit.')->group(function () {
        Route::middleware('business.auth:business')->prefix('purchase')->name('purchase.')->group(function () {
            Route::get('/', [CreditPurchaseController::class, 'index'])->name('index');
            Route::post('/', [CreditPurchaseController::class, 'store'])->name('store');
        });

        Route::get('/payments/{gateway}/callback', [CreditPurchaseCallbackController::class, 'show'])->name('purchase.callback');
    });

    // Live Negotiation Viewer (Phase 2, M7) — same 'business' guard as
    // the portal above, kept under its own prefix/name since it belongs
    // to the Negotiation domain, not Business.
    Route::middleware('business.auth:business')->prefix('nexus/negotiations')->name('nexus.negotiations.')->group(function () {
        Route::get('/', [NegotiationViewerController::class, 'index'])->name('index');
        Route::get('/{negotiation}', [NegotiationViewerController::class, 'show'])->name('show');
        Route::get('/{negotiation}/messages', [NegotiationViewerController::class, 'messages'])->name('messages');
        Route::post('/{negotiation}/approve', [NegotiationViewerController::class, 'approve'])->name('approve');
        Route::post('/{negotiation}/reject', [NegotiationViewerController::class, 'reject'])->name('reject');

        // Escrow (Phase 3, M4) — "Confirm Delivery"/"Dispute" on the deal
        // held against the auto-generated Contract.
        Route::post('/{negotiation}/escrow/release', [NegotiationViewerController::class, 'releaseEscrow'])->name('escrow.release');
        Route::post('/{negotiation}/escrow/dispute', [NegotiationViewerController::class, 'disputeEscrow'])->name('escrow.dispute');

        // Dispute Resolution evidence (Phase 6, M3) — appends a text note
        // to the auto-opened DisputeCase; a no-op if none exists yet.
        Route::post('/{negotiation}/dispute/evidence', [NegotiationViewerController::class, 'submitDisputeEvidence'])->name('dispute.evidence');

        // Reviews & Ratings (Phase 6, M1) — only reachable once Escrow is
        // Released (a genuinely completed deal), enforced in SubmitReviewAction.
        Route::post('/{negotiation}/review', [NegotiationViewerController::class, 'submitReview'])->name('review.submit');
    });

    // Viral Growth Engine (Phase 5) — its own prefix/name, same
    // 'business.auth' guard as the rest of the business-facing portal.
    Route::middleware('business.auth:business')->prefix('nexus/growth')->name('nexus.growth.')->group(function () {
        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
        Route::get('/invites', [InviteController::class, 'index'])->name('invites.index');
        Route::post('/invites', [InviteController::class, 'store'])->name('invites.store');

        Route::prefix('coalitions')->name('coalitions.')->group(function () {
            Route::get('/', [CoalitionController::class, 'index'])->name('index');
            Route::get('/create', [CoalitionController::class, 'create'])->name('create');
            Route::post('/', [CoalitionController::class, 'store'])->name('store');
            Route::get('/{coalition}', [CoalitionController::class, 'show'])->name('show');
            Route::post('/{coalition}/join', [CoalitionController::class, 'join'])->name('join');
            Route::post('/{coalition}/leave', [CoalitionController::class, 'leave'])->name('leave');
            Route::post('/{coalition}/close', [CoalitionController::class, 'close'])->name('close');
            Route::post('/{coalition}/cancel', [CoalitionController::class, 'cancel'])->name('cancel');
        });
    });

    // Network Visualization (Phase 5, M4) — Marketplace domain, same
    // 'business.auth' guard as the rest of the business-facing portal.
    Route::middleware('business.auth:business')->get('/nexus/network', [NetworkController::class, 'index'])->name('nexus.network.index');

    // Multi-Business Accounts (Phase 7, M1) — Holding domain, portal-only
    // (no MCP capability, same "human administrative structure" reasoning
    // Admin/Margin/LLM Settings already follow).
    Route::middleware('business.auth:business')->prefix('nexus/holding')->name('nexus.holding.')->group(function () {
        Route::get('/', [HoldingController::class, 'index'])->name('index');
        Route::get('/create', [HoldingController::class, 'create'])->name('create');
        Route::post('/', [HoldingController::class, 'store'])->name('store');
        Route::get('/{holding}', [HoldingController::class, 'show'])->name('show');
        Route::post('/{holding}/invite', [HoldingController::class, 'invite'])->name('invite');
        Route::post('/{holding}/subsidiaries/{subsidiary}/remove', [HoldingController::class, 'remove'])->name('subsidiaries.remove');
        Route::post('/subsidiaries/{subsidiary}/accept', [HoldingController::class, 'accept'])->name('subsidiaries.accept');
        Route::post('/subsidiaries/{subsidiary}/reject', [HoldingController::class, 'reject'])->name('subsidiaries.reject');
        Route::post('/subsidiaries/{subsidiary}/leave', [HoldingController::class, 'leave'])->name('subsidiaries.leave');
    });
});
