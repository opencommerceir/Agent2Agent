<?php

namespace App\Domains\Nexus\Contract\Domain\Repositories;

use App\Domains\Nexus\Contract\Domain\Entities\Contract;

interface ContractRepositoryInterface
{
    public function findById(int $id): ?Contract;

    public function findByNegotiationId(int $negotiationId): ?Contract;

    public function save(Contract $contract): Contract;
}
