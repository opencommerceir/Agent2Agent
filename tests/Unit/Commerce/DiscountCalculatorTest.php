<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\DiscountRule;
use App\Modules\Commerce\Domain\Entities\DiscountRuleCondition;
use App\Modules\Commerce\Domain\Services\DiscountCalculator;
use App\Modules\Commerce\Domain\ValueObjects\DiscountCondition;
use App\Modules\Commerce\Domain\ValueObjects\DiscountEvaluationContext;
use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DiscountCalculatorTest extends TestCase
{
    private DiscountCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DiscountCalculator();
    }

    private function makeRule(DiscountType $type, int $value, array $conditions = []): DiscountRule
    {
        return DiscountRule::create(
            tenantId: 1,
            name: 'Rule',
            description: null,
            discountType: $type,
            discountValue: $value,
            priority: new DiscountPriority(1),
            stackability: Stackability::Stackable,
            conditions: $conditions,
            startsAt: new DateTimeImmutable('-1 day'),
        );
    }

    public function test_percentage_computesPercentOfSubtotal(): void
    {
        $rule = $this->makeRule(DiscountType::Percentage, 10);
        $context = new DiscountEvaluationContext(8000, 'USD', []);

        $discount = $this->calculator->calculate($rule, $context);

        $this->assertSame(800, $discount->amount());
    }

    public function test_fixedAmount_neverExceedsSubtotal(): void
    {
        $rule = $this->makeRule(DiscountType::FixedAmount, 10000);
        $context = new DiscountEvaluationContext(500, 'USD', []);

        $discount = $this->calculator->calculate($rule, $context);

        $this->assertSame(500, $discount->amount());
    }

    public function test_buyXGetY_grantsCheapestMatchingUnitsFree(): void
    {
        $rule = $this->makeRule(DiscountType::BuyXGetY, 1, [
            new DiscountRuleCondition(DiscountCondition::MinQuantity, 2),
        ]);
        $context = new DiscountEvaluationContext(
            subtotalAmount: 6000,
            currency: 'USD',
            items: [
                ['productId' => 1, 'categoryId' => null, 'quantity' => 3, 'unitPriceAmount' => 2000],
            ],
        );

        $discount = $this->calculator->calculate($rule, $context);

        $this->assertSame(2000, $discount->amount()); // 1 free unit at $20
    }

    public function test_buyXGetY_withMultipleGetQuantity_grantsCheapestNUnitsFree(): void
    {
        $rule = $this->makeRule(DiscountType::BuyXGetY, 2, [
            new DiscountRuleCondition(DiscountCondition::MinQuantity, 2),
        ]);
        $context = new DiscountEvaluationContext(
            subtotalAmount: 5000,
            currency: 'USD',
            items: [
                ['productId' => 1, 'categoryId' => null, 'quantity' => 1, 'unitPriceAmount' => 3000],
                ['productId' => 2, 'categoryId' => null, 'quantity' => 1, 'unitPriceAmount' => 2000],
            ],
        );

        $discount = $this->calculator->calculate($rule, $context);

        $this->assertSame(5000, $discount->amount()); // both units free (cheapest-first)
    }

    public function test_tiered_withThresholds_picksHighestQualifyingTier(): void
    {
        $rule = $this->makeRule(DiscountType::Tiered, 5, [
            new DiscountRuleCondition(DiscountCondition::TieredThresholds, [
                ['min_subtotal' => 5000, 'percentage' => 10],
                ['min_subtotal' => 10000, 'percentage' => 20],
            ]),
        ]);
        $context = new DiscountEvaluationContext(12000, 'USD', []);

        $discount = $this->calculator->calculate($rule, $context);

        $this->assertSame(2400, $discount->amount()); // 20% tier
    }

    public function test_tiered_belowLowestThreshold_isZero(): void
    {
        $rule = $this->makeRule(DiscountType::Tiered, 5, [
            new DiscountRuleCondition(DiscountCondition::TieredThresholds, [
                ['min_subtotal' => 5000, 'percentage' => 10],
            ]),
        ]);
        $context = new DiscountEvaluationContext(1000, 'USD', []);

        $discount = $this->calculator->calculate($rule, $context);

        $this->assertSame(0, $discount->amount());
    }

    public function test_tiered_withNoThresholdsCondition_fallsBackToFlatPercentage(): void
    {
        $rule = $this->makeRule(DiscountType::Tiered, 15);
        $context = new DiscountEvaluationContext(10000, 'USD', []);

        $discount = $this->calculator->calculate($rule, $context);

        $this->assertSame(1500, $discount->amount());
    }
}
