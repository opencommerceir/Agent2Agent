<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A `DelegationRequest`'s own 1-10 priority (Phase 6, Stage 5, §7.30) —
 * caller-supplied, no default enforced *here* (`DelegateToAgentAction`
 * owns the request's own default of 5, mid-range, when a caller omits it,
 * HANDOFF §3 pattern #7). Recorded and validated, not yet load-bearing:
 * every delegation this stage runs synchronously and immediately, so
 * there is no real queue of *multiple pending* delegations for a numeric
 * priority to actually reorder — see `docs/multi-agent-collaboration.md`'s
 * own "Known scope decisions."
 */
final class DelegationPriority
{
    private const MIN = 1;

    private const MAX = 10;

    private readonly int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidArgumentException(
                "Delegation priority must be between ".self::MIN." and ".self::MAX.", got [{$value}]."
            );
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }
}
