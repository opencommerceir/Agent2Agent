<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ShippingMethodData;
use App\Modules\Shipping\Domain\Exceptions\ShippingMethodNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;

final class GetShippingMethodAction
{
    public function __construct(
        private readonly ShippingMethodRepositoryInterface $methods,
    ) {
    }

    public function execute(int $shippingMethodId, int $tenantId): ShippingMethodData
    {
        $method = $this->methods->findById($shippingMethodId, $tenantId);

        if (! $method) {
            throw new ShippingMethodNotFoundException("ShippingMethod [{$shippingMethodId}] does not exist.");
        }

        return ShippingMethodData::fromEntity($method);
    }
}
