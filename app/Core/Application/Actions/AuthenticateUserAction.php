<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\UserData;
use App\Core\Domain\Exceptions\InvalidCredentialsException;
use App\Core\Domain\Repositories\UserRepositoryInterface;

/**
 * Verifies email+password and returns the resolved identity — the DDD
 * counterpart to Agent's own AuthenticateAgentAction. Deliberately does
 * NOT touch Laravel's session/Auth facade at all: establishing the actual
 * authenticated session is the HTTP layer's job (LoginController calls
 * `Auth::loginUsingId()` after this Action confirms the credentials are
 * genuinely valid), the same split AgentAuthenticationService already
 * demonstrates between "verify identity" (Action) and "adapt it to this
 * transport" (thin HTTP-layer service/controller).
 */
final class AuthenticateUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function execute(string $email, string $plainPassword): UserData
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));

        if (! $user || ! $user->isActive() || ! $user->verifyPassword($plainPassword)) {
            throw new InvalidCredentialsException('The provided credentials do not match our records.');
        }

        return UserData::fromEntity($user);
    }
}
