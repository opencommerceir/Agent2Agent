<?php

namespace App\Modules\CRM\Domain\Repositories;

use App\Modules\CRM\Domain\Entities\CustomerNote;

interface CustomerNoteRepositoryInterface
{
    /**
     * @return list<CustomerNote>
     */
    public function listByCustomer(int $customerId, int $tenantId): array;

    public function save(CustomerNote $note): CustomerNote;
}
