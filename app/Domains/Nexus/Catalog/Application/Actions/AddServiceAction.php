<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ServiceData;
use App\Domains\Nexus\Catalog\Domain\Entities\Service;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;

final class AddServiceAction
{
    public function __construct(
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function execute(
        int $businessId,
        string $nameFa,
        string $nameEn,
        int $priceAmount,
        string $priceCurrency,
        ?int $durationMinutes = null,
        ?array $attributes = null,
    ): ServiceData {
        $service = Service::add(
            businessId: $businessId,
            nameFa: $nameFa,
            nameEn: $nameEn,
            hourlyPrice: Money::fromAmount($priceAmount, $priceCurrency),
            durationMinutes: $durationMinutes,
            attributes: $attributes,
        );
        $service = $this->services->save($service);

        return ServiceData::fromEntity($service);
    }
}
