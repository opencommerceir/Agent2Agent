<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\Capability;
use App\Core\Domain\ValueObjects\CapabilityName;

interface CapabilityRepositoryInterface
{
    public function findById(int $id): ?Capability;

    public function findByName(CapabilityName $name): ?Capability;

    /**
     * @return list<Capability>
     */
    public function all(): array;

    public function save(Capability $capability): Capability;
}
