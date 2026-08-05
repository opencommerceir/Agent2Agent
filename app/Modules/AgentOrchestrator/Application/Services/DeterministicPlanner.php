<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * The MVP planner named in Stage 1's own request, now reading its rules
 * from the calling Agent's own AgentProfile (§7.27) instead of Stage 1's
 * hardcoded per-agent-type keyword branches — `salesGrowthSteps()`/
 * `supportSteps()`/`financeSteps()` are gone; every persona's own
 * `config/agents/{type}.php` now supplies the equivalent shape.
 * `AgentProfile::getCapabilitiesForGoal()` does the keyword matching;
 * this class's only remaining job is resolving each capability's
 * config-declared *raw* default input into real, structurally-valid
 * values a capability's own `inputSchema` will accept.
 *
 * **Why resolution lives here and not on AgentProfile itself**: an
 * Entity must stay framework-free and side-effect-free (HANDOFF's
 * "Domain Entities have no Laravel" rule) — resolving `{date:-7}` needs
 * `now()`, resolving `{coupon_code}` needs randomness, both of which are
 * legitimately this Application-layer class's concern, the same way
 * Stage 1's own `lastNDays()`/`generateCouponCode()`/`parseDiscountPercent()`
 * helpers already were, just now applied to a profile's config-declared
 * templates instead of this class's own hardcoded step lists.
 *
 * **Recognized template tokens** (any other string value passes through
 * unresolved — a literal config value like `'percentage'`/`'weekly'`/
 * `'email'`):
 * - `{date:N}` — N days from today (negative for past, 0 for today),
 *   formatted `Y-m-d`.
 * - `{coupon_code}` — a freshly generated `COUPON-XXXXX` (see
 *   `CouponCode`'s own format requirement).
 * - `{discount_percent}` — parsed from the Goal's own text
 *   (`/(\d{1,3})\s*%/`), defaulting to 10 when absent, clamped 1-100 —
 *   identical parsing Stage 1's own hardcoded sales-growth plan already
 *   used.
 *
 * A real, documented correction from the original request's own literal
 * `config/agents/ceo.php` example (`'start_date' => '-7 days'`,
 * `'code' => 'AUTO_{date}'`): the former happens to parse as a valid PHP
 * relative date string but isn't the `Y-m-d` shape `report.sales.generate`
 * actually expects; the latter can never become a valid `COUPON-XXXXX`
 * code no matter how `{date}` is interpolated (wrong literal prefix
 * entirely). The `{date:N}`/`{coupon_code}` tokens above replace both,
 * shipped in this stage's own `config/agents/*.php` files instead of the
 * literal broken examples — see HANDOFF §7.27.
 */
final class DeterministicPlanner implements PlannerInterface
{
    private const DEFAULT_DISCOUNT_PERCENT = 10;

    public function createPlan(Goal $goal, AgentProfile $profile): ExecutionPlan
    {
        $steps = [];

        foreach ($profile->getCapabilitiesForGoal($goal->text) as $capability) {
            $rawInput = $profile->getDefaultInput($capability);
            $steps[] = new ExecutionStep($capability, $this->resolveInput($rawInput, $goal), Priority::Medium);
        }

        return new ExecutionPlan($goal, $steps);
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    private function resolveInput(array $rawInput, Goal $goal): array
    {
        $resolved = [];

        foreach ($rawInput as $field => $value) {
            $resolved[$field] = is_string($value) ? $this->resolveToken($value, $goal) : $value;
        }

        return $resolved;
    }

    private function resolveToken(string $value, Goal $goal): string|int
    {
        if (preg_match('/^\{date:(-?\d+)\}$/', $value, $matches) === 1) {
            return (new DateTimeImmutable('today'))->modify("{$matches[1]} days")->format('Y-m-d');
        }

        return match ($value) {
            '{coupon_code}' => 'COUPON-'.strtoupper(Str::random(5)),
            '{discount_percent}' => $this->parseDiscountPercent($goal->text),
            default => $value,
        };
    }

    private function parseDiscountPercent(string $text): int
    {
        if (preg_match('/(\d{1,3})\s*%/', $text, $matches) === 1) {
            return max(1, min(100, (int) $matches[1]));
        }

        return self::DEFAULT_DISCOUNT_PERCENT;
    }
}
