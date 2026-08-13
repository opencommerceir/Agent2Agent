<?php

use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessDashboardController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessLoginController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessLogoutController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessMfaChallengeController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessMfaController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessOauthController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessPasswordController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessSessionController;
use App\Domains\Nexus\Approval\Interfaces\Http\Controllers\ApprovalPolicyController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessTeamController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\RegisterBusinessController;
use App\Domains\Nexus\Credit\Interfaces\Http\Controllers\CreditPurchaseCallbackController;
use App\Domains\Nexus\Credit\Interfaces\Http\Controllers\CreditPurchaseController;
use App\Domains\Nexus\Growth\Interfaces\Http\Controllers\CoalitionController;
use App\Domains\Nexus\Growth\Interfaces\Http\Controllers\InviteController;
use App\Domains\Nexus\Growth\Interfaces\Http\Controllers\ReferralController;
use App\Domains\Nexus\Holding\Interfaces\Http\Controllers\HoldingController;
use App\Domains\Nexus\Marketplace\Interfaces\Http\Controllers\NetworkController;
use App\Domains\Nexus\Negotiation\Interfaces\Http\Controllers\NegotiationViewerController;
use App\Domains\Nexus\PrivateMarketplace\Interfaces\Http\Controllers\PrivateMarketplaceController;
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

            // Google OAuth (Phase 7/M6) — guest-only, same as the password
            // login form above; the link-confirmation step also happens
            // before the caller is logged in, so it lives in this group too.
            Route::prefix('oauth')->name('oauth.')->group(function () {
                Route::get('/{provider}/redirect', [BusinessOauthController::class, 'redirect'])->name('redirect');
                Route::get('/{provider}/callback', [BusinessOauthController::class, 'callback'])->name('callback');
                Route::get('/link', [BusinessOauthController::class, 'showLinkConfirmation'])->name('link.show');
                Route::post('/link', [BusinessOauthController::class, 'confirmLink'])->name('link.store');
            });

            // MFA login-time challenge (Phase 7/M7) — reachable only after
            // FinishesBusinessLogin::startMfaChallenge() stashes a pending
            // owner id in session; guest-guarded like the rest of this
            // group since the caller isn't authenticated yet.
            Route::prefix('mfa-challenge')->name('mfa-challenge.')->group(function () {
                Route::get('/', [BusinessMfaChallengeController::class, 'show'])->name('show');
                Route::post('/', [BusinessMfaChallengeController::class, 'verify'])->name('verify');
            });
        });

        Route::middleware('business.auth:business')->group(function () {
            Route::post('/logout', BusinessLogoutController::class)->name('logout');
            Route::get('/dashboard', [BusinessDashboardController::class, 'index'])->name('dashboard');
            Route::post('/dashboard/appeal', [BusinessDashboardController::class, 'submitSuspensionAppeal'])->name('dashboard.appeal');

            // Data Residency preference (Phase 7/M10) — see
            // DataResidencyRegion's own docblock for what "declaring" a
            // region actually means on a single-region platform.
            Route::post('/dashboard/data-residency', [BusinessDashboardController::class, 'updateDataResidency'])->name('dashboard.data-residency');

            // Phase 7/M3 — forced password change for a freshly-invited
            // team member (InviteTeamMemberAction's temporary password).
            Route::get('/password/force-change', [BusinessPasswordController::class, 'edit'])->name('password.force-change');
            Route::post('/password/force-change', [BusinessPasswordController::class, 'update'])->name('password.force-change.store');

            // Active session list/revoke (Phase 7/M6) — reads
            // sessions.user_id directly, the payoff of the login-time fix
            // in FinishesBusinessLogin.
            Route::prefix('sessions')->name('sessions.')->group(function () {
                Route::get('/', [BusinessSessionController::class, 'index'])->name('index');
                Route::delete('/{session}', [BusinessSessionController::class, 'destroy'])->name('destroy');
            });

            // MFA settings (Phase 7/M7) — one page, state driven by the
            // owner row itself (see BusinessMfaController's own docblock).
            Route::prefix('security/mfa')->name('mfa.')->group(function () {
                Route::get('/', [BusinessMfaController::class, 'edit'])->name('edit');
                Route::post('/start', [BusinessMfaController::class, 'start'])->name('start');
                Route::post('/confirm', [BusinessMfaController::class, 'confirm'])->name('confirm');
                Route::post('/disable', [BusinessMfaController::class, 'destroy'])->name('disable');
            });

            // Business Team Members & Roles (Phase 7/M3) — Owner-only
            // invite/role-change/remove, enforced inside each Action.
            Route::prefix('team')->name('team.')->group(function () {
                Route::get('/', [BusinessTeamController::class, 'index'])->name('index');
                Route::post('/', [BusinessTeamController::class, 'store'])->name('store');
                Route::post('/{member}/role', [BusinessTeamController::class, 'updateRole'])->name('role.update');
                Route::post('/{member}/remove', [BusinessTeamController::class, 'destroy'])->name('destroy');
            });

            // Multi-Level Approval Workflows (Phase 7/M4) — Owner-only
            // policy editor; the chain itself is enforced inside
            // AcceptDealAction/ApproveApprovalLevelAction, not here.
            Route::prefix('approval-policy')->name('approval-policy.')->group(function () {
                Route::get('/', [ApprovalPolicyController::class, 'edit'])->name('edit');
                Route::post('/', [ApprovalPolicyController::class, 'update'])->name('update');
            });
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
        Route::post('/{holding}/pooling', [HoldingController::class, 'togglePooling'])->name('pooling.toggle');
        Route::post('/{holding}/pool/contribute', [HoldingController::class, 'contribute'])->name('pool.contribute');
        Route::post('/{holding}/subsidiaries/{subsidiary}/remove', [HoldingController::class, 'remove'])->name('subsidiaries.remove');
        Route::post('/subsidiaries/{subsidiary}/accept', [HoldingController::class, 'accept'])->name('subsidiaries.accept');
        Route::post('/subsidiaries/{subsidiary}/reject', [HoldingController::class, 'reject'])->name('subsidiaries.reject');
        Route::post('/subsidiaries/{subsidiary}/leave', [HoldingController::class, 'leave'])->name('subsidiaries.leave');
    });

    // Private Marketplaces (Phase 7, M5) — invite-only groups with
    // confidentially-priced listings, same 'business.auth' guard as the
    // rest of the business-facing portal.
    Route::middleware('business.auth:business')->prefix('nexus/private-marketplaces')->name('nexus.private-marketplace.')->group(function () {
        Route::get('/', [PrivateMarketplaceController::class, 'index'])->name('index');
        Route::get('/create', [PrivateMarketplaceController::class, 'create'])->name('create');
        Route::post('/', [PrivateMarketplaceController::class, 'store'])->name('store');
        Route::get('/{marketplace}', [PrivateMarketplaceController::class, 'show'])->name('show');
        Route::post('/{marketplace}/archive', [PrivateMarketplaceController::class, 'archive'])->name('archive');
        Route::post('/{marketplace}/invite', [PrivateMarketplaceController::class, 'invite'])->name('invite');
        Route::post('/{marketplace}/members/{member}/remove', [PrivateMarketplaceController::class, 'removeMember'])->name('members.remove');
        Route::post('/members/{member}/accept', [PrivateMarketplaceController::class, 'accept'])->name('members.accept');
        Route::post('/members/{member}/reject', [PrivateMarketplaceController::class, 'reject'])->name('members.reject');
        Route::post('/members/{member}/leave', [PrivateMarketplaceController::class, 'leave'])->name('members.leave');
        Route::post('/{marketplace}/listings', [PrivateMarketplaceController::class, 'addListing'])->name('listings.store');
        Route::post('/{marketplace}/listings/{listing}/remove', [PrivateMarketplaceController::class, 'removeListing'])->name('listings.remove');
    });
});
