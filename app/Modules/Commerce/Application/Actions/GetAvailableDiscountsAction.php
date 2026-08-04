<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\DiscountRuleData;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Services\DiscountRuleEvaluator;
use App\Modules\Commerce\Domain\ValueObjects\DiscountEvaluationContext;
use DateTimeImmutable;

/**
 * A browsing/preview capability (`commerce.discount.available`): every
 * active DiscountRule that is *individually* eligible against this Cart
 * right now, each judged in isolation via `DiscountRuleEvaluator::evaluate()`.
 * Deliberately never runs `selectApplicableRules()` — "available" answers
 * "could this rule apply on its own," not "would it win the
 * priority/Stackability resolution against whatever else also qualifies,"
 * which is `ApplyDiscountsToCartAction`'s own, distinct question. An Agent
 * inspecting available discounts before deciding what to buy should see
 * every rule that could ever apply, not just the subset that would
 * currently win.
 */
final class GetAvailableDiscountsAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly DiscountRuleRepositoryInterface $rules,
        private readonly ProductRepositoryInterface $products,
        private readonly DiscountRuleEvaluator $evaluator,
    ) {
    }

    /**
     * @return list<DiscountRuleData>
     */
    public function execute(int $tenantId, int $agentId, int $cartId): array
    {
        $cart = $this->carts->findById($cartId, $tenantId);

        if (! $cart || $cart->ownerType() !== MemberType::Agent || $cart->ownerId() !== $agentId) {
            throw new CartNotFoundException("Cart [{$cartId}] does not exist.");
        }

        if ($cart->items() === []) {
            return [];
        }

        $context = $this->buildContext($cart, $tenantId);
        $now = new DateTimeImmutable();

        $activeRules = $this->rules->listByTenant($tenantId, isActive: true);

        return array_values(array_map(
            fn ($rule) => DiscountRuleData::fromEntity($rule),
            array_filter(
                $activeRules,
                fn ($rule) => $this->evaluator->evaluate($rule, $context, $now),
            ),
        ));
    }

    /**
     * Shared with ApplyDiscountsToCartAction: neither DiscountRuleEvaluator
     * nor DiscountCalculator may query a Repository themselves (see
     * DiscountEvaluationContext's own docblock), so every caller has to
     * assemble this context from a real Cart plus one bounded, per-item
     * Product lookup for categoryId.
     */
    private function buildContext(Cart $cart, int $tenantId): DiscountEvaluationContext
    {
        $currency = $cart->items()[0]->unitPrice()->currency();
        $subtotalAmount = array_sum(array_map(fn (CartItem $item) => $item->subtotalAmount(), $cart->items()));

        $items = array_map(function (CartItem $item) use ($tenantId) {
            $product = $this->products->findById($item->productId(), $tenantId);

            return [
                'productId' => $item->productId(),
                'categoryId' => $product?->categoryId(),
                'quantity' => $item->quantity()->value(),
                'unitPriceAmount' => $item->unitPrice()->amount(),
            ];
        }, $cart->items());

        return new DiscountEvaluationContext($subtotalAmount, $currency, $items);
    }
}
