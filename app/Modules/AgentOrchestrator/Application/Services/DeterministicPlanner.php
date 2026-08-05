<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;

/**
 * The MVP planner named in Stage 1's own request, now reading its rules
 * from the calling Agent's own AgentProfile (§7.27) instead of Stage 1's
 * hardcoded per-agent-type keyword branches — `salesGrowthSteps()`/
 * `supportSteps()`/`financeSteps()` are gone; every persona's own
 * `config/agents/{type}.php` now supplies the equivalent shape.
 * `AgentProfile::getCapabilitiesForGoal()` does the keyword matching;
 * this class's only remaining job is resolving each capability's
 * config-declared *raw* default input into real, structurally-valid
 * values a capability's own `inputSchema` will accept —
 * `AgentProfileInputResolver` (Phase 6, Stage 4, §7.29) now owns that
 * resolution, extracted out of this class the moment
 * `LearningService::suggestPlan()` needed the exact same tokens resolved
 * for a *learned* plan's own steps — see that class's own docblock for
 * the full reasoning and the recognized token list.
 *
 * A real, documented correction from the original request's own literal
 * `config/agents/ceo.php` example (`'start_date' => '-7 days'`,
 * `'code' => 'AUTO_{date}'`): the former happens to parse as a valid PHP
 * relative date string but isn't the `Y-m-d` shape `report.sales.generate`
 * actually expects; the latter can never become a valid `COUPON-XXXXX`
 * code no matter how `{date}` is interpolated (wrong literal prefix
 * entirely) — replaced with `{coupon_code}`. See
 * `AgentProfileInputResolver`'s own docblock for the full list of
 * recognized tokens.
 */
final class DeterministicPlanner implements PlannerInterface
{
    public function __construct(
        private readonly AgentProfileInputResolver $inputResolver = new AgentProfileInputResolver(),
    ) {
    }

    public function createPlan(Goal $goal, AgentProfile $profile): ExecutionPlan
    {
        $steps = [];

        foreach ($profile->getCapabilitiesForGoal($goal->text) as $capability) {
            $rawInput = $profile->getDefaultInput($capability);
            $steps[] = new ExecutionStep($capability, $this->inputResolver->resolve($rawInput, $goal), Priority::Medium);
        }

        return new ExecutionPlan($goal, $steps);
    }

    public function supportsLLM(): bool
    {
        return false;
    }
}
