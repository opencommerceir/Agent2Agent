<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\DiscountRuleCondition;

final class DiscountConditionData
{
    public function __construct(
        public readonly string $conditionType,
        public readonly mixed $conditionValue,
    ) {
    }

    public static function fromEntity(DiscountRuleCondition $condition): self
    {
        return new self($condition->type()->value, $condition->value());
    }

    /**
     * @return array{conditionType: string, conditionValue: mixed}
     */
    public function toArray(): array
    {
        return [
            'conditionType' => $this->conditionType,
            'conditionValue' => $this->conditionValue,
        ];
    }
}
