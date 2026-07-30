<?php

namespace App\Modules\Demo;

use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Demo\Application\Actions\CalculateAction;
use App\Modules\Demo\Application\Actions\EchoAction;
use App\Modules\Demo\Application\Actions\GetCurrentTimeAction;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Demo module's execution handlers — the descriptions
 * themselves (name, schema, permissions) are seeded separately by
 * DemoCapabilitiesSeeder, not here (see that class's docblock for why
 * DB-touching registration cannot safely live in boot()).
 *
 * registerHandlers() is pure in-memory (CapabilityHandlerRegistry holds
 * plain closures, no persistence), so it's always safe to run on every
 * boot regardless of migration state.
 *
 * Handlers now receive the caller's tenantId as a second argument
 * (Phase 2 decision, see CapabilityHandlerRegistry's docblock) — Demo's
 * Actions have no tenant-scoped data, so it's accepted here only to
 * satisfy the shared handler contract and otherwise ignored.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(CapabilityHandlerRegistry::class);

        $registry->register('demo.tools.echo', fn (array $input, int $tenantId) => $this->app->make(EchoAction::class)->execute($input));
        $registry->register('demo.tools.time', fn (array $input, int $tenantId) => $this->app->make(GetCurrentTimeAction::class)->execute($input));
        $registry->register('demo.tools.calculator', fn (array $input, int $tenantId) => $this->app->make(CalculateAction::class)->execute($input));
    }
}
