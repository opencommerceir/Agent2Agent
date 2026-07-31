<?php

namespace App\Modules\CRM\Domain\Entities;

use App\Modules\CRM\Domain\ValueObjects\TagName;
use DateTimeImmutable;

/**
 * A tenant-scoped label Customers (Commerce module, referenced only by
 * id via the customer_tag pivot — never a direct entity reference) can
 * be grouped under. Deliberately minimal, same reasoning Category gives
 * for staying flat: no hierarchy, no ordering, easy to extend later.
 */
final class Tag
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly TagName $name,
        private readonly ?string $color,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $tenantId, TagName $name, ?string $color = null): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            color: $color,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function name(): TagName
    {
        return $this->name;
    }

    public function color(): ?string
    {
        return $this->color;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
