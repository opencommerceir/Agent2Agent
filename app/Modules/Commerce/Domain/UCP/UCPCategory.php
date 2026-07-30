<?php

namespace App\Modules\Commerce\Domain\UCP;

final class UCPCategory
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $sourceSystem,
        public readonly string $name,
        public readonly ?string $parentExternalId = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['externalId'],
            sourceSystem: $data['sourceSystem'],
            name: $data['name'],
            parentExternalId: $data['parentExternalId'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,
            'sourceSystem' => $this->sourceSystem,
            'name' => $this->name,
            'parentExternalId' => $this->parentExternalId,
        ];
    }
}
