<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use InvalidArgumentException;

/**
 * The config-driven definition of one Agent persona (CEO, Sales, Support,
 * Finance, ...) — what `DeterministicPlanner` now reads instead of its own
 * previously-hardcoded keyword rules (§7.26). Adding a new Agent persona
 * is exactly one new `config/agents/{type}.php` file, no PHP change —
 * this Entity is the shape that file gets parsed into.
 *
 * Framework-free like every other Domain Entity in this codebase — reads
 * an already-fetched plain array (`fromConfig()`), never calls `config()`
 * itself (that belongs to `ConfigBasedAgentProfileRepository`,
 * Infrastructure). Deliberately holds no runtime state or side-effecting
 * behavior (no `now()`, no randomness) — `getCapabilitiesForGoal()`/
 * `getDefaultInput()` are both pure lookups; resolving a templated
 * default value (`{date:-7}`, `{coupon_code}`, ...) into a real runtime
 * value is `DeterministicPlanner`'s own job, not this Entity's — see that
 * class's own docblock.
 */
final class AgentProfile
{
    /**
     * @param array<string, list<string>> $planningRules goal-keyword => capability names, plus a required 'default'
     * @param array<string, array<string, mixed>> $defaultInputs capability name => raw (possibly templated) input
     * @param list<string> $permissions descriptive metadata only — the permission keys an operator should grant
     *        this Agent type for its own planned capabilities to actually succeed. Not enforced a second time by
     *        this module itself: `CapabilityToolInvoker` already checks each *planned* capability's own real
     *        `requiredPermissions` per step (the actual enforcement) — this list can drift from that over time if a
     *        profile's own `planning_rules` change without updating it, a real, honest gap (HANDOFF §8), not a
     *        silently-assumed guarantee.
     */
    private function __construct(
        public readonly AgentType $type,
        public readonly string $name,
        public readonly string $description,
        private readonly array $planningRules,
        private readonly array $defaultInputs,
        public readonly array $permissions,
    ) {
    }

    /**
     * @param array<string, mixed> $config the raw array a config/agents/{type}.php file returns
     */
    public static function fromConfig(AgentType $type, array $config): self
    {
        foreach (['name', 'description', 'planning_rules', 'default_inputs', 'permissions'] as $key) {
            if (! array_key_exists($key, $config)) {
                throw new InvalidArgumentException(
                    "Agent profile config for [{$type->value}] is missing required key [{$key}]."
                );
            }
        }

        if (! array_key_exists('default', $config['planning_rules'])) {
            throw new InvalidArgumentException(
                "Agent profile config for [{$type->value}] is missing a required 'default' planning rule."
            );
        }

        return new self(
            type: $type,
            name: $config['name'],
            description: $config['description'],
            planningRules: $config['planning_rules'],
            defaultInputs: $config['default_inputs'],
            permissions: $config['permissions'],
        );
    }

    /**
     * The first planning rule whose keyword appears in the Goal's own
     * text wins (case-insensitive substring match, first match in config
     * declaration order) — the same dispatch shape Stage 1's own
     * `DeterministicPlanner::createPlan()` used, now table-driven instead
     * of a hardcoded `match(true)` block. Falls back to `'default'` when
     * nothing matches — never an empty plan by surprise, unlike Stage 1
     * (where an unrecognized goal produced zero steps); a profile's own
     * `'default'` rule is what a future LLM-less catch-all should be.
     *
     * @return list<string>
     */
    public function getCapabilitiesForGoal(string $goalText): array
    {
        $text = mb_strtolower($goalText);

        foreach ($this->planningRules as $keyword => $capabilities) {
            if ($keyword === 'default') {
                continue;
            }

            if (str_contains($text, mb_strtolower($keyword))) {
                return $capabilities;
            }
        }

        return $this->planningRules['default'];
    }

    /**
     * @return array<string, mixed> the raw, possibly-templated default input — never resolved here
     */
    public function getDefaultInput(string $capability): array
    {
        return $this->defaultInputs[$capability] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function planningRules(): array
    {
        return $this->planningRules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function defaultInputs(): array
    {
        return $this->defaultInputs;
    }
}
