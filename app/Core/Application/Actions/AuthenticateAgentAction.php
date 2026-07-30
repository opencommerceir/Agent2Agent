<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\AgentData;
use App\Core\Domain\Entities\AgentToken;
use App\Core\Domain\Exceptions\AgentNotActiveException;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\AgentTokenRepositoryInterface;

/**
 * One Action = one business operation: verify a bearer token and resolve
 * the Agent identity behind it. This is the only place in Core that turns
 * a raw credential into a trusted AgentData — MCP Gateway (a future module)
 * will call this on every incoming Agent request instead of touching
 * tokens or repositories directly.
 */
final class AuthenticateAgentAction
{
    public function __construct(
        private readonly AgentTokenRepositoryInterface $tokens,
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(string $plainToken): AgentData
    {
        $token = $this->tokens->findByHash(AgentToken::hash($plainToken));

        if (! $token || ! $token->isValid()) {
            throw new InvalidAgentTokenException('The provided agent token is invalid, revoked, or expired.');
        }

        $agent = $this->agents->findById($token->agentId());

        if (! $agent) {
            throw new InvalidAgentTokenException('The provided agent token is invalid, revoked, or expired.');
        }

        if (! $agent->isActive()) {
            throw new AgentNotActiveException("Agent [{$agent->name()}] is not active.");
        }

        $token->markUsed();
        $this->tokens->save($token);

        return AgentData::fromEntity($agent);
    }
}
