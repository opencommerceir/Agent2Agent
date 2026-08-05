<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Resolves an `AgentProfile`'s own raw, possibly-templated `default_inputs`
 * entry into real, structurally-valid values a capability's own
 * `inputSchema` will accept — extracted out of `DeterministicPlanner`
 * (Phase 6, Stage 4, §7.29) the moment a *second* caller needed the exact
 * same resolution: `LearningService::suggestPlan()` builds a suggested
 * `ExecutionStep` from a learned pattern's own capability list using this
 * same Agent persona's own `default_inputs`, and a raw, unresolved
 * `'{date:-7}'` string reaching a real capability (e.g.
 * `report.sales.generate`'s own `start_date`) fails that capability's own
 * validation exactly the same way it would have before `DeterministicPlanner`
 * existed at all. Keeping one resolver both classes depend on avoids a
 * second, independently-drifting implementation of the same token
 * vocabulary — the identical "reuse, don't duplicate" reasoning this whole
 * stage's own planning already applied to `ExecutionMemoryRepositoryInterface`
 * (see `docs/execution-memory.md`).
 *
 * Deliberately Application-layer, not Domain — resolving `{date:-7}` needs
 * `now()`, resolving `{coupon_code}` needs randomness, neither of which
 * belongs on a framework-free Domain Entity/Service (HANDOFF's "Domain
 * Entities have no Laravel" rule, the same reasoning `DeterministicPlanner`'s
 * own docblock already gave for why this logic never lived on `AgentProfile`
 * itself).
 *
 * **Recognized template tokens** (any other string value passes through
 * unresolved — a literal config value like `'percentage'`/`'weekly'`):
 * - `{date:N}` — N days from today (negative for past, 0 for today),
 *   formatted `Y-m-d`.
 * - `{coupon_code}` — a freshly generated `COUPON-XXXXX`.
 * - `{discount_percent}` — parsed from the Goal's own text
 *   (`/(\d{1,3})\s*%/`), defaulting to 10 when absent, clamped 1-100.
 */
final class AgentProfileInputResolver
{
    private const DEFAULT_DISCOUNT_PERCENT = 10;

    /**
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    public function resolve(array $rawInput, Goal $goal): array
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
