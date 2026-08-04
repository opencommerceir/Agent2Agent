<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\DiscountRuleData;
use App\Modules\Commerce\Domain\Entities\DiscountRule;
use App\Modules\Commerce\Domain\Entities\DiscountRuleCondition;
use App\Modules\Commerce\Domain\Events\DiscountRuleWasCreated;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\DiscountCondition;
use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

/**
 * `conditions` (name => value, e.g. `[['type' => 'min_quantity', 'value' => 2]]`)
 * is taken at face value and frozen into the rule at creation — the same
 * "structure fixed, generic fields aren't" shape `CreateVariantAttributeAction`
 * already establishes for its own `values` input (§7.21). No registry-level
 * check that a condition's own shape matches what `DiscountRuleEvaluator`
 * expects for its type — a malformed condition simply never matches
 * anything at evaluation time rather than being rejected at creation,
 * the same deliberate looseness `Product.attributes` already has.
 */
final class CreateDiscountRuleAction
{
    public function __construct(
        private readonly DiscountRuleRepositoryInterface $rules,
    ) {
    }

    /**
     * @param list<array{type: string, value: mixed}> $conditions
     */
    public function execute(
        int $tenantId,
        string $name,
        string $discountType,
        int $discountValue,
        int $priority,
        string $stackability,
        array $conditions = [],
        ?string $description = null,
        ?string $startsAt = null,
        ?string $expiresAt = null,
        ?int $maxUses = null,
    ): DiscountRuleData {
        $ruleConditions = array_map(
            fn (array $condition) => new DiscountRuleCondition(
                DiscountCondition::from($condition['type']),
                $condition['value'],
            ),
            $conditions,
        );

        $rule = DiscountRule::create(
            tenantId: $tenantId,
            name: $name,
            description: $description,
            discountType: DiscountType::from($discountType),
            discountValue: $discountValue,
            priority: new DiscountPriority($priority),
            stackability: Stackability::from($stackability),
            conditions: $ruleConditions,
            startsAt: $startsAt !== null ? new DateTimeImmutable($startsAt) : new DateTimeImmutable(),
            expiresAt: $expiresAt !== null ? new DateTimeImmutable($expiresAt) : null,
            maxUses: $maxUses,
        );
        $rule = $this->rules->save($rule);

        Event::dispatch(new DiscountRuleWasCreated($rule));

        return DiscountRuleData::fromEntity($rule);
    }
}
