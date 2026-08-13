<?php

namespace App\Domains\Nexus\Holding\Domain\Repositories;

use App\Domains\Nexus\Holding\Domain\Entities\Holding;

interface HoldingRepositoryInterface
{
    public function findById(int $id): ?Holding;

    /**
     * A Business administers at most one Holding — enforced here (unique
     * index on parent_business_id at the DB layer) rather than allowing a
     * parent to spin up several.
     */
    public function findByParentBusinessId(int $parentBusinessId): ?Holding;

    public function save(Holding $holding): Holding;
}
