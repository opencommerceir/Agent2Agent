<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\AppliedDiscountData;
use App\Modules\Commerce\Domain\Entities\AppliedDiscount;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\AppliedDiscountRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Services\DiscountCalculator;
use App\Modules\Commerce\Domain\Services\DiscountRuleEvaluator;
use App\Modules\Commerce\Domain\ValueObjects\DiscountEvaluationContext;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * `commerce.discount.apply`: resolves the *winning* subset of active
 * DiscountRules against a Cart (via `DiscountRuleEvaluator::selectApplicableRules()`,
 * priority + Stackability already fully implemented there) and replaces
 * the Cart's whole AppliedDiscount set with it — the same
 * "recompute the whole collection, delete-and-reinsert" shape
 * `EloquentCartRepository::save()` already uses for CartItem, mirrored
 * here by `AppliedDiscountRepositoryInterface::replaceForCart()`. Calling
 * this again after the Cart changed simply produces a new winning set and
 * replaces the old one wholesale — never an append.
 *
 * Deliberately never calls `DiscountRule::recordUsage()` and never saves a
 * DiscountRule: a Cart is a mutable, disposable preview, not a completed
 * purchase, the same reason `Coupon::usedCount` is only ever incremented
 * at real checkout (ApplyCouponAction, owned by a sibling agent's own
 * work this stage), never here.
 *
 * `appliedToProductIds` on each resulting AppliedDiscount is every
 * productId considered from the Cart, not an attempt to attribute a
 * BuyXGetY rule's discount to the specific cheapest units it actually
 * discounted — no per-unit attribution is tracked anywhere in this
 * design (DiscountCalculator's own docblock), so recording it here would
 * be a level of precision nothing downstream can use or verify. A
 * documented simplification, not a bug.
 */
final class ApplyDiscountsToCartAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly DiscountRuleRepositoryInterface $rules,
        private readonly ProductRepositoryInterface $products,
        private readonly AppliedDiscountRepositoryInterface $appliedDiscounts,
        private readonly DiscountRuleEvaluator $evaluator,
        private readonly DiscountCalculator $calculator,
    ) {
    }

    /**
     * @return array{appliedDiscounts: list<AppliedDiscountData>, totalDiscountAmount: int, totalDiscountCurrency: string}
     */
    public function execute(int $tenantId, int $agentId, int $cartId): array
    {
        $cart = $this->carts->findById($cartId, $tenantId);

        if (! $cart || $cart->ownerType() !== MemberType::Agent || $cart->ownerId() !== $agentId) {
            throw new CartNotFoundException("Cart [{$cartId}] does not exist.");
        }

        if ($cart->items() === []) {
            throw new InvalidArgumentException('Cart is empty.');
        }

        $context = $this->buildContext($cart, $tenantId);
        $now = new DateTimeImmutable();

        $activeRules = $this->rules->listByTenant($tenantId, isActive: true);
        $winningRules = $this->evaluator->selectApplicableRules($activeRules, $context, $now);

        $productIds = array_map(fn (array $item) => $item['productId'], $context->items);

        $applied = array_map(
            fn ($rule) => new AppliedDiscount(
                discountRuleId: $rule->id(),
                couponId: null,
                discountType: $rule->discountType(),
                discountAmount: $this->calculator->calculate($rule, $context),
                appliedToProductIds: $productIds,
            ),
            $winningRules,
        );

        $this->appliedDiscounts->replaceForCart($cartId, $tenantId, $applied);

        $totalDiscountAmount = array_sum(array_map(
            fn (AppliedDiscount $discount) => $discount->discountAmount()->amount(),
            $applied,
        ));

        return [
            'appliedDiscounts' => array_map(fn (AppliedDiscount $discount) => AppliedDiscountData::fromEntity($discount), $applied),
            'totalDiscountAmount' => $totalDiscountAmount,
            'totalDiscountCurrency' => $context->currency,
        ];
    }

    /**
     * Shared with GetAvailableDiscountsAction: neither DiscountRuleEvaluator
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
