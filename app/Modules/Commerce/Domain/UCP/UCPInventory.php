<?php

namespace App\Modules\Commerce\Domain\UCP;

final class UCPInventory
{
    public function __construct(
        public readonly string $productExternalId,
        public readonly string $sourceSystem,
        public readonly int $quantityAvailable,
        public readonly bool $tracksInventory = true,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productExternalId: $data['productExternalId'],
            sourceSystem: $data['sourceSystem'],
            quantityAvailable: $data['quantityAvailable'],
            tracksInventory: $data['tracksInventory'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'productExternalId' => $this->productExternalId,
            'sourceSystem' => $this->sourceSystem,
            'quantityAvailable' => $this->quantityAvailable,
            'tracksInventory' => $this->tracksInventory,
        ];
    }
}
