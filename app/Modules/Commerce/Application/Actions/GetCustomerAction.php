<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\CustomerData;
use App\Modules\Commerce\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;

final class GetCustomerAction
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function execute(int $id, int $tenantId): CustomerData
    {
        $customer = $this->customers->findById($id, $tenantId);

        if (! $customer) {
            throw new CustomerNotFoundException("Customer [{$id}] does not exist.");
        }

        return CustomerData::fromEntity($customer);
    }
}
