<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\AgentTokenData;
use App\Core\Domain\Entities\AgentToken;
use App\Core\Domain\Repositories\AgentTokenRepositoryInterface;
use DateTimeImmutable;

/**
 * One Action = one business operation: issue a new credential for an
 * already-registered Agent.
 *
 * The plain token is generated here with random_bytes() (a CSPRNG, not a
 * framework helper) and is returned to the caller exactly once inside the
 * DTO. Only its SHA-256 hash — computed via AgentToken::hash() — is ever
 * persisted, satisfying the "raw token never stored" rule.
 */
final class GenerateAgentTokenAction
{
    public function __construct(
        private readonly AgentTokenRepositoryInterface $tokens,
    ) {
    }

    public function execute(int $agentId, ?string $label = null, ?DateTimeImmutable $expiresAt = null): AgentTokenData
    {
        $plainToken = 'oc_agent_'.bin2hex(random_bytes(32));

        $token = AgentToken::issue(
            agentId: $agentId,
            tokenHash: AgentToken::hash($plainToken),
            label: $label,
            expiresAt: $expiresAt,
        );

        $token = $this->tokens->save($token);

        return AgentTokenData::issued($token, $plainToken);
    }
}
