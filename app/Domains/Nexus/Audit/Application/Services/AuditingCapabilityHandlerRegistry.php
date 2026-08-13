<?php

namespace App\Domains\Nexus\Audit\Application\Services;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Domains\Nexus\Audit\Application\Actions\RecordAuditEntryAction;
use Illuminate\Contracts\Foundation\Application;

/**
 * Decorates Core's CapabilityHandlerRegistry so every Nexus capability
 * handler gets a hash-chained audit entry (Phase 7/M9), wired at the one
 * place all ~20 of them are already registered
 * (NexusServiceProvider::registerMcpCapabilityHandlers() and its
 * sub-methods) — instead of editing each individual
 * `$handlers->register(...)` call site. Same "one central choke point
 * beats N scattered ones" lesson Phase 6/M4's suspension check already
 * established via ResolveActingBusinessAction.
 *
 * Exposes the exact same register(string, callable): void signature the
 * real CapabilityHandlerRegistry does, so every existing call site needed
 * zero changes — only the `$handlers = $this->app->make(...)` line at the
 * top of registerMcpCapabilityHandlers() changed to construct this
 * instead. hasHandler()/getHandler() are never called through this
 * decorator (Core's CapabilityExecutionService reads directly from the
 * real registry this wraps), so this intentionally implements register()
 * only, not the full registry surface.
 */
final class AuditingCapabilityHandlerRegistry
{
    public function __construct(
        private readonly CapabilityHandlerRegistry $inner,
        private readonly Application $app,
    ) {
    }

    public function register(string $capabilityName, callable $handler): void
    {
        $this->inner->register($capabilityName, function (array $input, AuthContext $context) use ($capabilityName, $handler) {
            return $this->app->make(RecordAuditEntryAction::class)->wrap(
                $capabilityName,
                $context,
                $input,
                fn () => $handler($input, $context),
            );
        });
    }
}
