<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\AgentToken;

interface AgentTokenRepositoryInterface
{
    public function findByHash(string $tokenHash): ?AgentToken;

    public function save(AgentToken $token): AgentToken;
}
