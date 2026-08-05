<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * Which persona initiated a Goal — routing/classification metadata
 * recorded on every Execution, not a dispatch key the MVP
 * DeterministicPlanner actually branches on (it keys off the Goal's own
 * text instead — see that class's own docblock). A future LLM-based
 * planner is the natural place for this to start actually shaping the
 * plan (e.g. a Finance Agent's goals never touching commerce.coupon.*).
 */
enum AgentType: string
{
    case Ceo = 'ceo';
    case Sales = 'sales';
    case Support = 'support';
    case Finance = 'finance';
}
