<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\CustomerData;
use App\Modules\Commerce\Domain\Entities\Customer;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\CustomerStatus;

/**
 * Backs the `commerce.customer.list` MCP capability — takes the raw
 * `array $input` MCP Gateway received plus tenantId, doubling directly
 * as the callable CommerceServiceProvider::boot() registers, the same
 * pattern ListProductsAction/ListOrdersAction established.
 */
final class ListCustomersAction
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{customers: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $status = isset($input['status']) && is_string($input['status'])
            ? CustomerStatus::tryFrom($input['status'])
            : null;

        $limit = isset($input['limit']) && is_int($input['limit'])
            ? max(1, min($input['limit'], self::MAX_LIMIT))
            : self::DEFAULT_LIMIT;

        $customers = $this->customers->listByTenant($tenantId, $status, $limit);

        return [
            'customers' => array_map(
                fn (Customer $customer) => CustomerData::fromEntity($customer)->toArray(),
                $customers,
            ),
        ];
    }
}
