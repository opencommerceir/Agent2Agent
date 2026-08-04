<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\DiscountRuleData;
use App\Modules\Commerce\Domain\Exceptions\DiscountRuleNotFoundException;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use DateTimeImmutable;

/**
 * `conditions` are not updatable — frozen at creation, same reasoning
 * DiscountRule's own docblock gives.
 */
final class UpdateDiscountRuleAction
{
    public function __construct(
        private readonly DiscountRuleRepositoryInterface $rules,
    ) {
    }

    public function execute(
        int $id,
        int $tenantId,
        string $name,
        int $discountValue,
        int $priority,
        string $stackability,
        ?string $description = null,
        ?string $startsAt = null,
        ?string $expiresAt = null,
        bool $isActive = true,
    ): DiscountRuleData {
        $rule = $this->rules->findById($id, $tenantId);

        if (! $rule) {
            throw new DiscountRuleNotFoundException("DiscountRule [{$id}] does not exist.");
        }

        $rule->update(
            name: $name,
            description: $description,
            discountValue: $discountValue,
            priority: new DiscountPriority($priority),
            stackability: Stackability::from($stackability),
            startsAt: $startsAt !== null ? new DateTimeImmutable($startsAt) : $rule->startsAt(),
            expiresAt: $expiresAt !== null ? new DateTimeImmutable($expiresAt) : $rule->expiresAt(),
            isActive: $isActive,
        );

        $rule = $this->rules->save($rule);

        return DiscountRuleData::fromEntity($rule);
    }
}
