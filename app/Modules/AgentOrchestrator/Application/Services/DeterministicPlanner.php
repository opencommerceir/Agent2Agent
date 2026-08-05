<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * The MVP planner named in this module's own request: a small, hardcoded
 * set of keyword rules over the Goal's own text, each rule producing a
 * fixed, ordered list of *real, already-existing* MCP capabilities with
 * concrete input this planner itself fills in.
 *
 * Two deliberate departures from this module's own request's literal
 * pseudocode, both documented on this class rather than silently:
 *
 * 1. **Every capability name below is a real, already-registered
 *    capability** (verified against `docs/agent-orchestrator.md`'s own
 *    mapping table and this codebase's live Capability Registry) — the
 *    request's own illustrative names (`reporting.sales.summary`,
 *    `analytics.top_products`, `inventory.check`) do not exist anywhere
 *    in this codebase. `analytics.kpi.calculate` is reused twice, once
 *    per `KPIType` (`top_products`/`low_stock_products`), rather than
 *    inventing two capabilities Analytics never defined.
 * 2. **Every step's `input` is filled with concrete values**, not left
 *    empty — `CapabilityExecutionService`'s own `MCPRequestValidationService`
 *    rejects a call missing any field a capability's `inputSchema`
 *    declares, so an empty `input` would make every step in the request's
 *    own worked example fail validation before ever reaching a Domain
 *    Module. This is still orchestration, not business logic: it never
 *    decides what a "good" discount or campaign message *is* (that
 *    remains the promoted request's own domain-module Actions' job) — it
 *    only supplies structurally-valid, deterministic default parameters
 *    (a date range, a random coupon code) the same way any tool-calling
 *    orchestrator must. A future LLM-based Planner is the natural place
 *    for genuinely reasoned parameter values instead of these fixed
 *    defaults — see this module's own README.
 *
 * Reused across rules: `notification.message.send`'s `type` uses
 * `NotificationType::PromotionAnnouncement` — a new, purely additive enum
 * case added to Notifications for this module (the same "purely additive
 * new case" shape `NotificationType::SubscriptionPaymentFailed` already
 * established, HANDOFF §7.25) since none of the 5 pre-existing types
 * (order_placed, shipment_status_changed, points_earned, ticket_created,
 * subscription_payment_failed) fit "a marketing/promotional message,"
 * and misusing an unrelated existing type would have been worse than one
 * small, honest addition. `recipient` is a fixed placeholder address —
 * a Goal's own free text carries no real customer/segment list to notify,
 * and building one is out of scope for this module (no business logic) —
 * documented, not silently faked as a real send to real customers.
 */
final class DeterministicPlanner implements PlannerInterface
{
    private const DEFAULT_DISCOUNT_PERCENT = 10;

    private const PROMOTION_NOTIFICATION_TYPE = 'promotion_announcement';

    private const PLACEHOLDER_RECIPIENT = 'marketing@opencommerce.local';

    public function createPlan(Goal $goal): ExecutionPlan
    {
        $text = mb_strtolower($goal->text);

        $steps = match (true) {
            str_contains($text, 'sales') => $this->salesGrowthSteps($goal),
            str_contains($text, 'support') || str_contains($text, 'ticket') => $this->supportSteps(),
            str_contains($text, 'finance') || str_contains($text, 'revenue') || str_contains($text, 'invoice') => $this->financeSteps(),
            default => [],
        };

        return new ExecutionPlan($goal, $steps);
    }

    /**
     * @return list<ExecutionStep>
     */
    private function salesGrowthSteps(Goal $goal): array
    {
        [$startDate, $endDate] = $this->lastNDays(7);
        $discountPercent = $this->parseDiscountPercent($goal->text);

        return [
            new ExecutionStep(
                'report.sales.generate',
                ['start_date' => $startDate, 'end_date' => $endDate],
                Priority::High,
            ),
            new ExecutionStep(
                'analytics.kpi.calculate',
                ['kpi_type' => 'top_products', 'time_period' => 'weekly', 'start_date' => $startDate, 'end_date' => $endDate],
                Priority::Medium,
            ),
            new ExecutionStep(
                'analytics.kpi.calculate',
                ['kpi_type' => 'low_stock_products', 'time_period' => 'weekly', 'start_date' => $startDate, 'end_date' => $endDate],
                Priority::Medium,
            ),
            new ExecutionStep(
                'commerce.coupon.create',
                [
                    'code' => $this->generateCouponCode(),
                    'discount_type' => 'percentage',
                    'discount_value' => $discountPercent,
                ],
                Priority::Low,
            ),
            new ExecutionStep(
                'notification.message.send',
                [
                    'type' => self::PROMOTION_NOTIFICATION_TYPE,
                    'channel' => 'email',
                    'recipient' => self::PLACEHOLDER_RECIPIENT,
                    'variables' => ['discount_percent' => (string) $discountPercent],
                ],
                Priority::Low,
            ),
        ];
    }

    /**
     * @return list<ExecutionStep>
     */
    private function supportSteps(): array
    {
        return [
            new ExecutionStep('crm.ticket.list', ['status' => 'open'], Priority::High),
        ];
    }

    /**
     * @return list<ExecutionStep>
     */
    private function financeSteps(): array
    {
        [$startDate, $endDate] = $this->lastNDays(30);

        return [
            new ExecutionStep(
                'report.revenue.generate',
                ['start_date' => $startDate, 'end_date' => $endDate],
                Priority::High,
            ),
            new ExecutionStep('finance.invoice.list', ['status' => 'issued'], Priority::Medium),
        ];
    }

    /**
     * @return array{0: string, 1: string} [startDate, endDate] both Y-m-d
     */
    private function lastNDays(int $days): array
    {
        $end = new DateTimeImmutable('today');
        $start = $end->modify("-{$days} days");

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function parseDiscountPercent(string $text): int
    {
        if (preg_match('/(\d{1,3})\s*%/', $text, $matches) === 1) {
            return max(1, min(100, (int) $matches[1]));
        }

        return self::DEFAULT_DISCOUNT_PERCENT;
    }

    private function generateCouponCode(): string
    {
        return 'COUPON-'.strtoupper(Str::random(5));
    }
}
