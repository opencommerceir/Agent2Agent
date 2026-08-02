<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\VariantAttributeData;
use App\Modules\Commerce\Domain\Entities\VariantAttribute;
use App\Modules\Commerce\Domain\Repositories\VariantAttributeRepositoryInterface;

/**
 * Not named in the original request's own Action list (only
 * CreateVariantAttributeAction was) — added unprompted (HANDOFF §3
 * pattern #12): the request's own capability list names
 * `commerce.variant.attribute.list` (renamed to `commerce.attribute.list`,
 * §7.21), which needs a real Action behind it.
 */
final class ListVariantAttributesAction
{
    public function __construct(
        private readonly VariantAttributeRepositoryInterface $attributes,
    ) {
    }

    /**
     * @return list<VariantAttributeData>
     */
    public function execute(int $tenantId): array
    {
        return array_map(
            fn (VariantAttribute $attribute) => VariantAttributeData::fromEntity($attribute),
            $this->attributes->listByTenant($tenantId),
        );
    }
}
