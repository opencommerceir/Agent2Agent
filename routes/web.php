<?php

use App\Core\Interfaces\HTTP\Controllers\Auth\LoginController;
use App\Core\Interfaces\HTTP\Controllers\Auth\LogoutController;
use App\Http\Controllers\Dashboard\AgentController;
use App\Http\Controllers\Dashboard\AnalyticsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\NexusDisputeController;
use App\Http\Controllers\Dashboard\NexusEscrowController;
use App\Http\Controllers\Dashboard\NexusFraudController;
use App\Http\Controllers\Dashboard\NexusVerificationController;
use App\Http\Controllers\Dashboard\NexusGrowthController;
use App\Http\Controllers\Dashboard\NexusLlmSettingsController;
use App\Http\Controllers\Dashboard\NexusMarginSettingsController;
use App\Http\Controllers\Dashboard\NexusRevenueController;
use App\Http\Controllers\Dashboard\NexusSsoProvidersController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\PerformanceController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\TenantController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Showcase\ShowcaseAccessController;
use App\Http\Controllers\Showcase\ShowcaseController;
use App\Http\Controllers\Showcase\ShowcasePanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Not behind 'auth'/'guest' — a language pick should work from the login
// page too, before anyone is signed in.
Route::get('/language/{code}', LanguageController::class)->name('language.switch');

// The `/showcase` chat UI — deliberately outside 'auth'/'guest' too. It
// is a public demo surface authenticated against the seeded Demo Agent's
// own bearer token (a fresh one per browser session, see
// ShowcaseController's own docblock), never the Dashboard's human User
// session, so it carries none of the 'auth'/'admin' middleware above.
Route::prefix('showcase')->name('showcase.')->group(function () {
    // The passcode gate itself (Phase 3, §7.33) — deliberately outside
    // the 'showcase.access' middleware below: this is the one route that
    // must stay reachable to ever grant access in the first place.
    Route::get('/enter', [ShowcaseAccessController::class, 'create'])->name('enter');
    Route::post('/enter', [ShowcaseAccessController::class, 'store'])->name('enter.store');

    Route::middleware('showcase.access')->group(function () {
        Route::get('/', [ShowcaseController::class, 'index'])->name('index');
        Route::post('/chat', [ShowcaseController::class, 'chat'])->name('chat');

        // Conversation history (Phase 3, §7.33) — reads this Tenant's own
        // past Executions back through the same read-only Actions
        // `/api/agents/executions[/{id}]` already use.
        Route::get('/history', [ShowcaseController::class, 'history'])->name('history');
        Route::get('/history/{execution}', [ShowcaseController::class, 'historyShow'])->name('history.show');

        // The live side panel (Phase 2, §7.33) — each tab its own route
        // so the Alpine panel can refresh exactly the active tab, never
        // all three at once.
        Route::prefix('panel')->name('panel.')->group(function () {
            Route::get('/products', [ShowcasePanelController::class, 'products'])->name('products');
            Route::get('/orders', [ShowcasePanelController::class, 'orders'])->name('orders');
            Route::get('/kpis', [ShowcasePanelController::class, 'kpis'])->name('kpis');
        });
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::middleware('admin')->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenantId}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenantId}', [TenantController::class, 'update'])->name('tenants.update');

        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('/agents/create', [AgentController::class, 'create'])->name('agents.create');
        Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
        Route::get('/agents/{agentId}/edit', [AgentController::class, 'edit'])->name('agents.edit');
        Route::put('/agents/{agentId}', [AgentController::class, 'update'])->name('agents.update');
        Route::post('/agents/{agentId}/suspend', [AgentController::class, 'suspend'])->name('agents.suspend');
        Route::post('/agents/{agentId}/activate', [AgentController::class, 'activate'])->name('agents.activate');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{productId}', [ProductController::class, 'show'])->name('products.show');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{orderId}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/export/csv', [AnalyticsController::class, 'exportCsv'])->name('analytics.export.csv');
        Route::get('/analytics/export/pdf', [AnalyticsController::class, 'exportPdf'])->name('analytics.export.pdf');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');

        // Nexus (Phase 3) — admin-only Escrow dispute resolution.
        Route::prefix('nexus')->name('nexus.')->group(function () {
            Route::get('/escrows', [NexusEscrowController::class, 'index'])->name('escrows.index');
            Route::post('/escrows/{escrow}/refund', [NexusEscrowController::class, 'refund'])->name('escrows.refund');

            // Dispute Resolution (Phase 6/M3) — evidence/mediation/arbitration
            // queue layered on top of the Escrow dispute flag above.
            Route::get('/disputes', [NexusDisputeController::class, 'index'])->name('disputes.index');
            Route::post('/disputes/{dispute}/mediate', [NexusDisputeController::class, 'mediate'])->name('disputes.mediate');
            Route::post('/disputes/{dispute}/arbitrate', [NexusDisputeController::class, 'arbitrate'])->name('disputes.arbitrate');

            Route::get('/margin-settings', [NexusMarginSettingsController::class, 'index'])->name('margin-settings.index');
            Route::put('/margin-settings', [NexusMarginSettingsController::class, 'update'])->name('margin-settings.update');

            // SSO Providers (Phase 7/M8) — read-only "what's live vs.
            // stubbed" view over SsoProviderRegistry.
            Route::get('/sso-providers', [NexusSsoProvidersController::class, 'index'])->name('sso-providers.index');

            Route::get('/revenue', [NexusRevenueController::class, 'index'])->name('revenue.index');

            Route::get('/llm-settings', [NexusLlmSettingsController::class, 'index'])->name('llm-settings.index');
            Route::put('/llm-settings', [NexusLlmSettingsController::class, 'update'])->name('llm-settings.update');
            Route::post('/llm-settings/test-connection', [NexusLlmSettingsController::class, 'testConnection'])->name('llm-settings.test-connection');

            Route::get('/growth', [NexusGrowthController::class, 'index'])->name('growth.index');

            // Fraud Detection + Suspension (Phase 6/M4).
            Route::get('/fraud', [NexusFraudController::class, 'index'])->name('fraud.index');
            Route::post('/fraud/run-detection', [NexusFraudController::class, 'runDetection'])->name('fraud.run-detection');
            Route::post('/fraud/suspend', [NexusFraudController::class, 'suspend'])->name('fraud.suspend');
            Route::post('/fraud/{business}/reactivate', [NexusFraudController::class, 'reactivate'])->name('fraud.reactivate');
            Route::post('/fraud/appeals/{appeal}/resolve', [NexusFraudController::class, 'resolveAppeal'])->name('fraud.appeals.resolve');

            // Verification System (Phase 6/M5).
            Route::get('/verification', [NexusVerificationController::class, 'index'])->name('verification.index');
            Route::post('/verification/businesses/{business}/verify', [NexusVerificationController::class, 'verifyBusiness'])->name('verification.businesses.verify');
            Route::post('/verification/products/{product}/verify', [NexusVerificationController::class, 'verifyProduct'])->name('verification.products.verify');
            Route::post('/verification/products/{product}/reject', [NexusVerificationController::class, 'rejectProduct'])->name('verification.products.reject');
            Route::post('/verification/services/{service}/verify', [NexusVerificationController::class, 'verifyService'])->name('verification.services.verify');
            Route::post('/verification/services/{service}/reject', [NexusVerificationController::class, 'rejectService'])->name('verification.services.reject');
        });
    });
});
