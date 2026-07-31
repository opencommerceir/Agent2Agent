<?php

namespace App\Modules\Workflows\Application\Listeners;

use App\Modules\Workflows\Application\Actions\TriggerWorkflowAction;

/**
 * Scaffolding only — deliberately NOT registered against any event in
 * WorkflowsServiceProvider::boot() this stage (same explicit-scope
 * reasoning as CartAbandonedListener: only Low Stock Alert is wired).
 *
 * Unlike CartAbandonedListener, this one has no technical blocker —
 * Commerce's `OrderWasPlaced` event already exists and already carries
 * the placed Order (including its `total()`), so a real
 * `handle(OrderWasPlaced $event): void` here would be a same-shape
 * addition to InventoryLowListener's, calling
 * `TriggerWorkflowAction::execute($event->order->tenantId(), EventType::OrderHighValue->value, ['total_amount' => $event->order->total()->amount(), ...])`.
 * It is left unwired purely because this stage's request scoped the
 * *functional* Workflow to Low Stock Alert only — "بقیه Workflows بعداً
 * اضافه می‌شوند" — not because anything is missing to build it.
 */
final class HighValueOrderListener
{
    public function __construct(
        private readonly TriggerWorkflowAction $triggerWorkflow,
    ) {
    }
}
