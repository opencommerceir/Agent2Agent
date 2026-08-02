<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\VariantAttributeData;
use App\Modules\Commerce\Domain\Entities\VariantAttribute;
use App\Modules\Commerce\Domain\Exceptions\DuplicateVariantAttributeException;
use App\Modules\Commerce\Domain\Repositories\VariantAttributeRepositoryInterface;

/**
 * Creates a tenant-scoped VariantAttribute together with every value it
 * will ever have (the request's own `$values` list) — VariantAttribute's
 * own docblock explains why there is no separate "add a value later"
 * Action this stage.
 */
final class CreateVariantAttributeAction
{
    public function __construct(
        private readonly VariantAttributeRepositoryInterface $attributes,
    ) {
    }

    /**
     * @param list<string> $values
     */
    public function execute(int $tenantId, string $name, array $values, int $displayOrder = 0): VariantAttributeData
    {
        if ($this->attributes->nameExists($name, $tenantId)) {
            throw new DuplicateVariantAttributeException("Variant attribute [{$name}] already exists for this tenant.");
        }

        $attribute = VariantAttribute::create($tenantId, $name, $values, $displayOrder);
        $attribute = $this->attributes->save($attribute);

        return VariantAttributeData::fromEntity($attribute);
    }
}
