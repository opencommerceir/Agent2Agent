<?php

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
| Phase 0: infrastructure only. Business domains add their own route
| groups here as they land.
|
*/

Route::middleware('web')->group(function () {
    Route::get('/nexus', fn () => view('nexus::welcome'))->name('nexus.welcome');
});
