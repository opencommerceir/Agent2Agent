<?php

namespace App\Modules\AgentOrchestrator\Application\Prompts;

use App\Core\Application\DTOs\CapabilityData;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;

/**
 * Builds the prompt `LLMPlanner` sends to whichever provider is
 * configured. Pure string formatting — no LLM-specific concerns (that's
 * `LLMClientInterface`'s job) — so it's usable, and independently
 * testable, regardless of which provider ends up reading it.
 */
final class PlanningPromptTemplate
{
    /**
     * @param list<CapabilityData> $capabilities every capability currently registered on the platform
     */
    public static function forGoal(Goal $goal, AgentProfile $profile, array $capabilities): string
    {
        $capabilityList = self::formatCapabilities($capabilities);
        $permissionHint = $profile->permissions === []
            ? 'none declared'
            : implode(', ', $profile->permissions);

        return <<<PROMPT
        You are an AI agent orchestrator. Your task is to create an execution plan to achieve the following business goal by invoking existing platform capabilities — you do not perform any action yourself, you only choose which capabilities to call, in what order, and with what input.

        Goal: {$goal->text}
        Agent persona: {$profile->name} ({$profile->type->value})
        Agent persona description: {$profile->description}
        This persona typically holds these permissions: {$permissionHint}

        Available capabilities:
        {$capabilityList}

        Create a step-by-step plan using ONLY the capabilities listed above (use each capability's exact `name`). For each step:
        1. Choose the most appropriate capability for this part of the goal.
        2. Provide input that satisfies every field listed in that capability's own inputSchema — use today's date and reasonable defaults where a concrete value isn't otherwise implied by the goal (dates as YYYY-MM-DD strings).
        3. Order steps so that any step whose output later steps might reasonably depend on comes first.
        4. Do not invent a capability name that isn't in the list above.

        Return your plan as a JSON object with a single "steps" array, matching the JSON Schema you were given, and nothing else.
        PROMPT;
    }

    /**
     * @param list<CapabilityData> $capabilities
     */
    private static function formatCapabilities(array $capabilities): string
    {
        return implode("\n", array_map(
            fn (CapabilityData $capability) => sprintf(
                '- %s: %s (inputSchema: %s)',
                $capability->name,
                $capability->description,
                json_encode($capability->inputSchema),
            ),
            $capabilities,
        ));
    }
}
