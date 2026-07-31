<?php

namespace App\Modules\Workflows;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Commerce\Domain\Events\InventoryWasCommitted;
use App\Modules\Workflows\Application\Actions\CreateWorkflowAction;
use App\Modules\Workflows\Application\Actions\GetWorkflowAction;
use App\Modules\Workflows\Application\Actions\ListWorkflowLogsAction;
use App\Modules\Workflows\Application\Actions\ListWorkflowsAction;
use App\Modules\Workflows\Application\Actions\TriggerWorkflowAction;
use App\Modules\Workflows\Application\DTOs\WorkflowData;
use App\Modules\Workflows\Application\Listeners\InventoryLowListener;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;
use App\Modules\Workflows\Infrastructure\Repositories\EloquentWorkflowRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Workflows module — Phase 3, Stage 3, built on Phase 1/2's
 * infrastructure and Phase 3.1/3.2's Module -> Module pattern without
 * changing Commerce's public contracts (only additively: the new
 * `InventoryWasCommitted` event — see its own docblock). Workflows
 * depends on Commerce's `InventoryRepositoryInterface`/
 * `ProductRepositoryInterface` (via `InventoryLowListener`) the same
 * Dependency Inversion direction CRM/Finance already established.
 *
 * This module introduces the platform's first real Domain Event Listener
 * wired across a module boundary — every event dispatched since Phase 1
 * had zero registered listeners until now (`Event::listen()` calls
 * simply didn't exist anywhere in this codebase). Only
 * `InventoryLowListener` is actually registered below;
 * `CartAbandonedListener`/`HighValueOrderListener` exist as documented
 * scaffolding (see their own docblocks) and are deliberately not
 * `Event::listen()`'d to anything this stage.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows Commerce's/
 * CRM's/Finance's seeder pattern instead (WorkflowsCapabilitiesSeeder),
 * same RefreshDatabase-ordering reason documented there.
 */
class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WorkflowRepositoryInterface::class, EloquentWorkflowRepository::class);
    }

    public function boot(): void
    {
        Event::listen(InventoryWasCommitted::class, InventoryLowListener::class);

        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('workflow.definition.create', function (array $input, AuthContext $context) {
            /** @var WorkflowData $workflow */
            $workflow = $this->app->make(CreateWorkflowAction::class)->execute(
                tenantId: $context->tenantId,
                name: $input['name'],
                description: $input['description'] ?? null,
                eventType: $input['event_type'],
                rules: $input['rules'],
                actions: $input['actions'],
            );

            return ['workflow' => $workflow->toArray()];
        });

        $handlers->register('workflow.definition.get', function (array $input, AuthContext $context) {
            /** @var WorkflowData $workflow */
            $workflow = $this->app->make(GetWorkflowAction::class)->execute((int) $input['workflow_id'], $context->tenantId);

            return ['workflow' => $workflow->toArray()];
        });

        $handlers->register(
            'workflow.definition.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListWorkflowsAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register(
            'workflow.event.trigger',
            fn (array $input, AuthContext $context) => $this->app->make(TriggerWorkflowAction::class)->execute(
                tenantId: $context->tenantId,
                eventType: $input['event_type'],
                eventData: $input['event_data'],
            ),
        );

        $handlers->register(
            'workflow.log.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListWorkflowLogsAction::class)->execute($input, $context->tenantId),
        );
    }
}
