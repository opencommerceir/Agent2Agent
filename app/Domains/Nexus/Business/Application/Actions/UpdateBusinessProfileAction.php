<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use InvalidArgumentException;

final class UpdateBusinessProfileAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function execute(int $businessId, string $nameFa, string $nameEn, BusinessType $type, Industry $industry): BusinessData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $business->updateProfile($nameFa, $nameEn, $type, $industry);
        $business = $this->businesses->save($business);

        return BusinessData::fromEntity($business);
    }
}
