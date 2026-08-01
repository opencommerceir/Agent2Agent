<?php

use App\Core\Interfaces\HTTP\Controllers\Auth\LoginController;
use App\Core\Interfaces\HTTP\Controllers\Auth\LogoutController;
use App\Http\Controllers\Dashboard\AgentController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\TenantController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Not behind 'auth'/'guest' — a language pick should work from the login
// page too, before anyone is signed in.
Route::get('/language/{code}', LanguageController::class)->name('language.switch');

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

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
