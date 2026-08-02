<?php

namespace App\Modules\Commerce\Domain\Entities;

use DateTimeImmutable;

/**
 * A tenant-scoped, reusable label ("Color", "Size") — defined once per
 * Tenant, referenced by many Products' own variants, not owned by any
 * single Product. Its `values` (VariantAttributeValue) are frozen at
 * creation, mirroring `Workflow`'s own "rules/actions frozen at
 * creation" shape (§7.9): CreateVariantAttributeAction's own input names
 * every value this attribute will ever have, all at once — there is no
 * "add a value to an existing attribute" operation this stage, the same
 * documented gap Workflows' own rules/actions already carry (§8.25).
 */
final class VariantAttribute
{
    /**
     * @param list<VariantAttributeValue> $values
     */
    private function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly string $name,
        private readonly int $displayOrder,
        private readonly array $values,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<string> $values
     */
    public static function create(int $tenantId, string $name, array $values, int $displayOrder = 0): self
    {
        $valueEntities = [];

        foreach ($values as $index => $value) {
            $valueEntities[] = VariantAttributeValue::create($value, $index);
        }

        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            displayOrder: $displayOrder,
            values: $valueEntities,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param list<VariantAttributeValue> $values
     */
    public static function reconstitute(?int $id, int $tenantId, string $name, int $displayOrder, array $values, DateTimeImmutable $createdAt): self
    {
        return new self($id, $tenantId, $name, $displayOrder, $values, $createdAt);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * @return list<VariantAttributeValue>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
