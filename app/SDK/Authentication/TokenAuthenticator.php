<?php

namespace App\SDK\Authentication;

/**
 * Holds the agent token and knows exactly one thing: how to turn it into
 * the `Authorization: Bearer {token}` header MCP Gateway expects.
 */
final class TokenAuthenticator
{
    public function __construct(
        private readonly string $token,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }
}
