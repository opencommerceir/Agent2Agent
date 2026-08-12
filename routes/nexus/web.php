<?php

use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessDashboardController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessLoginController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\BusinessLogoutController;
use App\Domains\Nexus\Business\Interfaces\Http\Controllers\RegisterBusinessController;
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
        });
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
    });
});
