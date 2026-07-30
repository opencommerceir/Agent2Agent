<?php

namespace App\Core\Application\Services;

use App\Core\Application\Actions\AuthenticateAgentAction;
use App\Core\Application\DTOs\AgentData;
use App\Core\Domain\Exceptions\InvalidAgentTokenException;
use Illuminate\Http\Request;

/**
 * Thin adapter between the HTTP bearer-token header and
 * AuthenticateAgentAction. Agent auth is deliberately not wired through
 * Laravel's guard/session system (config/auth.php) — that system models
 * human Users logging into a browser; Agents are a separate Core concept
 * authenticated purely by a hashed bearer token, and forcing it through
 * the guard abstraction would be more "magic" for no real benefit
 * (Explicit Over Magic).
 */
final class AgentAuthenticationService
{
    public function __construct(
        private readonly AuthenticateAgentAction $authenticateAgent,
    ) {
    }

    public function authenticateFromRequest(Request $request): AgentData
    {
        $token = $request->bearerToken();

        if (! $token) {
            throw new InvalidAgentTokenException('No agent token provided.');
        }

        return $this->authenticateAgent->execute($token);
    }
}
