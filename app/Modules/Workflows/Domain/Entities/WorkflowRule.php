<?php

namespace App\Modules\Workflows\Domain\Entities;

use App\Modules\Workflows\Domain\ValueObjects\Threshold;

/**
 * One condition a Workflow's matching event data must satisfy. No `id`/
 * `workflowId` property on the Domain Entity — same "no id field on a
 * child entity with no independent identity" shape OrderItem/Discount/
 * CRM's TicketComment/Finance's InvoiceItem already established,
 * inherited here even though the `workflow_rules` table has an `id`
 * primary key column like every table does.
 *
 * conditionType is a plain string, not an enum — WorkflowEvaluator is
 * the single place that knows the finite set it currently understands
 * (less_than/greater_than/equals); keeping it a string here means adding
 * a new condition type never requires a migration or touching this
 * Entity, only WorkflowEvaluator's own match arm.
 */
final class WorkflowRule
{
    private function __construct(
        private readonly string $conditionType,
        private readonly string $field,
        private readonly Threshold $threshold,
    ) {
    }

    public static function create(string $conditionType, string $field, Threshold $threshold): self
    {
        return new self($conditionType, $field, $threshold);
    }

    public function conditionType(): string
    {
        return $this->conditionType;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function threshold(): Threshold
    {
        return $this->threshold;
    }
}
