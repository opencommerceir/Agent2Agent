<?php

namespace App\Modules\Workflows\Application\Listeners;

use App\Modules\Workflows\Application\Actions\TriggerWorkflowAction;

/**
 * Scaffolding only — deliberately NOT registered against any event in
 * WorkflowsServiceProvider::boot() this stage (per this stage's explicit
 * scope: "فعلاً فقط یک Workflow ساده پیاده‌سازی می‌کنیم" — only Low Stock
 * Alert is wired). `EventType::CartAbandoned` is already a real,
 * usable value — a Workflow can be created against it today via
 * `workflow.definition.create` — but nothing yet calls TriggerWorkflowAction
 * for it.
 *
 * The reason this can't simply mirror InventoryLowListener's shape:
 * "cart abandoned for 24 hours" is a time-based condition, not a
 * reaction to something that just happened — Commerce has no Domain
 * Event for "this Cart has been sitting idle," and dispatching one would
 * require a scheduled job periodically polling Carts (Laravel's
 * scheduler, not an Event Listener), which doesn't exist anywhere in
 * this codebase yet. That scheduling mechanism, not this class, is the
 * actual missing piece.
 */
final class CartAbandonedListener
{
    public function __construct(
        private readonly TriggerWorkflowAction $triggerWorkflow,
    ) {
    }
}
