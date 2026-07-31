<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ShippingMethodData;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;

final class ListShippingMethodsAction
{
    public function __construct(
        private readonly ShippingMethodRepositoryInterface $methods,
    ) {
    }

    /**
     * @param array{is_active?: bool} $input
     * @return array{methods: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $isActive = array_key_exists('is_active', $input) ? (bool) $input['is_active'] : null;

        $methods = $this->methods->list($tenantId, $isActive);

        return [
            'methods' => array_map(fn ($method) => ShippingMethodData::fromEntity($method)->toArray(), $methods),
        ];
    }
}
