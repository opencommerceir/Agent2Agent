<?php

use App\Console\Commands\DetectFraudSignalsCommand;
use App\Console\Commands\ExpireLoyaltyPointsCommand;
use App\Console\Commands\GenerateAnalyticsSnapshotCommand;
use App\Console\Commands\MarkAbandonedCartsCommand;
use App\Console\Commands\ProcessAutomationRulesCommand;
use App\Console\Commands\ProcessDueSubscriptionsCommand;
use App\Console\Commands\WarmCacheCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| Laravel 11+ has no app/Console/Kernel.php — Schedule::command() calls
| here are the idiomatic replacement (already wired via bootstrap/app.php's
| withRouting(commands: 'routes/console.php')). The scheduler itself still
| needs a real OS cron entry running `php artisan schedule:run` every
| minute in any actual deployment — these calls only define *what* runs,
| not that anything triggers them automatically.
|
*/

Schedule::command(ExpireLoyaltyPointsCommand::class)->daily()->at('02:00')->withoutOverlapping();

Schedule::command(MarkAbandonedCartsCommand::class)->hourly()->withoutOverlapping();

Schedule::command(GenerateAnalyticsSnapshotCommand::class)->daily()->at('01:00')->withoutOverlapping();

// Phase 4 Stage 8 (Performance Optimization, §7.20) — flushes the entire
// cache store immediately before rewarming it, per the request's own
// explicit ask: a stale cached Product/KPI value from before a deploy
// should never survive past midnight, and warming right after a full
// flush means the very next real request of the day always hits a warm
// cache instead of racing a cold one.
Schedule::command(WarmCacheCommand::class)->daily()->at('00:00')->withoutOverlapping()->before(fn () => Cache::flush());

// Phase 5 Stage 5 (Subscriptions & Recurring Orders, §7.25) — the recurring
// billing engine's scheduled entry point: queues a Job per Subscription due
// for renewal and per SubscriptionInvoice due for a retry attempt.
Schedule::command(ProcessDueSubscriptionsCommand::class)->daily()->at('00:00')->withoutOverlapping();

// Nexus Phase 6/M4 — rule-based fraud detection; hourly is frequent enough
// that a Business crossing the dispute-loss threshold is caught soon
// after an arbiter's ruling, without re-scanning constantly.
Schedule::command(DetectFraudSignalsCommand::class)->hourly()->withoutOverlapping();

// Nexus Phase 8/M4 — Automation Workflows engine (recurring orders,
// inventory alerts, price alerts); hourly matches DetectFraudSignalsCommand's
// own cadence reasoning above — frequent enough that a due rule fires soon
// after its condition becomes true, without re-scanning constantly.
Schedule::command(ProcessAutomationRulesCommand::class)->hourly()->withoutOverlapping();
