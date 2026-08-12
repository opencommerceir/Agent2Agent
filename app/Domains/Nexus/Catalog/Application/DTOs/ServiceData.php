<?php

namespace App\Domains\Nexus\Catalog\Application\DTOs;

use App\Domains\Nexus\Catalog\Domain\Entities\Service;

final class ServiceData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $businessId,
        public readonly string $nameFa,
        public readonly string $nameEn,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly ?int $durationMinutes,
        public readonly ?array $attributes,
        public readonly string $verificationStatus,
    ) {
    }

    public static function fromEntity(Service $service): self
    {
        return new self(
            id: $service->id(),
            businessId: $service->businessId(),
            nameFa: $service->nameFa(),
            nameEn: $service->nameEn(),
            priceAmount: $service->hourlyPrice()->amount(),
            priceCurrency: $service->hourlyPrice()->currency(),
            durationMinutes: $service->durationMinutes(),
            attributes: $service->attributes(),
            verificationStatus: $service->verificationStatus()->value,
        );
    }

    /**
     * @return array{id: ?int, businessId: int, nameFa: string, nameEn: string, priceAmount: int, priceCurrency: string, durationMinutes: ?int, attributes: ?array, verificationStatus: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessId' => $this->businessId,
            'nameFa' => $this->nameFa,
            'nameEn' => $this->nameEn,
            'priceAmount' => $this->priceAmount,
            'priceCurrency' => $this->priceCurrency,
            'durationMinutes' => $this->durationMinutes,
            'attributes' => $this->attributes,
            'verificationStatus' => $this->verificationStatus,
        ];
    }
}
