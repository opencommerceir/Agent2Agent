<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Customer;
use App\Modules\Commerce\Domain\ValueObjects\CustomerStatus;
use App\Modules\Commerce\Domain\ValueObjects\Email;

interface CustomerRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Customer;

    public function findByEmail(Email $email, int $tenantId): ?Customer;

    public function emailExists(Email $email, int $tenantId): bool;

    /**
     * @return list<Customer>
     */
    public function listByTenant(int $tenantId, ?CustomerStatus $status, int $limit): array;

    public function save(Customer $customer): Customer;
}
