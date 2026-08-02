<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\VariantAttribute;
use App\Modules\Commerce\Domain\Entities\VariantAttributeValue;

final class VariantAttributeData
{
    /**
     * @param list<array{id: ?int, value: string, displayOrder: int}> $values
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly int $displayOrder,
        public readonly array $values,
    ) {
    }

    public static function fromEntity(VariantAttribute $attribute): self
    {
        return new self(
            id: $attribute->id(),
            tenantId: $attribute->tenantId(),
            name: $attribute->name(),
            displayOrder: $attribute->displayOrder(),
            values: array_map(
                fn (VariantAttributeValue $value) => [
                    'id' => $value->id(),
                    'value' => $value->value(),
                    'displayOrder' => $value->displayOrder(),
                ],
                $attribute->values(),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'displayOrder' => $this->displayOrder,
            'values' => $this->values,
        ];
    }
}
