<?php

namespace App\Domains\Nexus\Contract\Domain\Repositories;

use App\Domains\Nexus\Contract\Domain\Entities\DisputeCase;
use App\Domains\Nexus\Contract\Domain\ValueObjects\DisputeCaseStatus;

interface DisputeCaseRepositoryInterface
{
    public function findById(int $id): ?DisputeCase;

    public function findByEscrowId(int $escrowId): ?DisputeCase;

    /**
     * @return list<DisputeCase>
     */
    public function findByStatus(DisputeCaseStatus $status): array;

    public function save(DisputeCase $disputeCase): DisputeCase;
}
