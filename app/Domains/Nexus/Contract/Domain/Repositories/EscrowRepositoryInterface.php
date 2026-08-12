<?php

namespace App\Domains\Nexus\Contract\Domain\Repositories;

use App\Domains\Nexus\Contract\Domain\Entities\Escrow;
use App\Domains\Nexus\Contract\Domain\ValueObjects\EscrowStatus;

interface EscrowRepositoryInterface
{
    public function findById(int $id): ?Escrow;

    public function findByContractId(int $contractId): ?Escrow;

    public function findByNegotiationId(int $negotiationId): ?Escrow;

    /**
     * @return list<Escrow>
     */
    public function findByStatus(EscrowStatus $status): array;

    public function save(Escrow $escrow): Escrow;
}
