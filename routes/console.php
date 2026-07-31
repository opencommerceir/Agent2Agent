<?php

use App\Console\Commands\ExpireLoyaltyPointsCommand;
use App\Console\Commands\MarkAbandonedCartsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
