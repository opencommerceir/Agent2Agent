<?php

namespace App\Domains\Nexus\Business\Domain\Repositories;

use App\Domains\Nexus\Business\Domain\Entities\SuspensionAppeal;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAppealStatus;

interface SuspensionAppealRepositoryInterface
{
    public function findById(int $id): ?SuspensionAppeal;

    /**
     * @return list<SuspensionAppeal>
     */
    public function findByStatus(SuspensionAppealStatus $status): array;

    public function save(SuspensionAppeal $appeal): SuspensionAppeal;
}
