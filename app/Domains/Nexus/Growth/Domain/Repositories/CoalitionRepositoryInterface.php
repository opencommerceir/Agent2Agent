<?php

namespace App\Domains\Nexus\Growth\Domain\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\Coalition;

interface CoalitionRepositoryInterface
{
    public function findById(int $id): ?Coalition;

    /**
     * @return list<Coalition>
     */
    public function findByStatus(string $status): array;

    public function findByNegotiationId(int $negotiationId): ?Coalition;

    public function save(Coalition $coalition): Coalition;
}
