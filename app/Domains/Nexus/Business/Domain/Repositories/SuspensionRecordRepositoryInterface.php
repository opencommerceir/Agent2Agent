<?php

namespace App\Domains\Nexus\Business\Domain\Repositories;

use App\Domains\Nexus\Business\Domain\Entities\SuspensionRecord;

interface SuspensionRecordRepositoryInterface
{
    /**
     * @return list<SuspensionRecord>
     */
    public function findByBusinessId(int $businessId): array;

    public function save(SuspensionRecord $record): SuspensionRecord;
}
