<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ServiceData;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use InvalidArgumentException;

final class UpdateServiceAction
{
    public function __construct(
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function execute(
        int $serviceId,
        int $businessId,
        string $nameFa,
        string $nameEn,
        int $priceAmount,
        string $priceCurrency,
        ?int $durationMinutes,
        ?array $attributes,
    ): ServiceData {
        $service = $this->services->findById($serviceId);

        if (! $service) {
            throw new InvalidArgumentException("Service [{$serviceId}] does not exist.");
        }

        if ($service->businessId() !== $businessId) {
            throw new InvalidArgumentException("Service [{$serviceId}] does not belong to this Business.");
        }

        $service->update($nameFa, $nameEn, Money::fromAmount($priceAmount, $priceCurrency), $durationMinutes, $attributes);
        $service = $this->services->save($service);

        return ServiceData::fromEntity($service);
    }
}
