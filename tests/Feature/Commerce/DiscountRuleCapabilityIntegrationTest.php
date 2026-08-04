<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\ApplyDiscountsToCartAction;
use App\Modules\Commerce\Application\Actions\CreateDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\GetAvailableDiscountsAction;
use App\Modules\Commerce\Application\Actions\GetDiscountRuleAction;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\AppliedDiscountRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves GetAvailableDiscountsAction/ApplyDiscountsToCartAction wire the
 * already-unit-tested DiscountRuleEvaluator/DiscountCalculator correctly
 * end to end against a real Cart/DB — not re-testing the priority/
 * Stackability resolution algorithm itself (that's covered where
 * DiscountRuleEvaluator lives), only that these two Actions feed it the
 * right context and persist/report the result correctly.
 */
class DiscountRuleCapabilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_applyDiscounts_selectsWinningStackableSubset_available_returnsAllEligible(): void
    {
        [$tenantId, $agentId] = $this->registerAgent();
        $cartId = $this->buildCartWithItems($tenantId, $agentId, quantity: 4, unitPriceAmount: 1000);

        // A: 10% off, priority 10, stackable — always eligible.
        $ruleA = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: 'A: 10% off',
            discountType: 'percentage',
            discountValue: 10,
            priority: 10,
            stackability: 'stackable',
        );

        // B: $5 off, priority 5, exclusive — eligible, but loses to A's higher priority.
        $ruleB = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: 'B: $5 off',
            discountType: 'fixed_amount',
            discountValue: 500,
            priority: 5,
            stackability: 'exclusive',
        );

        // C: Buy 2 Get 1, priority 1, stackable — eligible (min_quantity 2 met by 4 units) and same Stackability as A.
        $ruleC = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: 'C: Buy 2 Get 1',
            discountType: 'buy_x_get_y',
            discountValue: 1,
            priority: 1,
            stackability: 'stackable',
            conditions: [['type' => 'min_quantity', 'value' => 2]],
        );

        // .available: all 3 rules are individually eligible on their own.
        $available = app(GetAvailableDiscountsAction::class)->execute($tenantId, $agentId, $cartId);
        $this->assertCount(3, $available);
        $availableIds = array_map(fn ($rule) => $rule->id, $available);
        $this->assertContains($ruleA->id, $availableIds);
        $this->assertContains($ruleB->id, $availableIds);
        $this->assertContains($ruleC->id, $availableIds);

        // .apply: only A and C actually win (B's Exclusive Stackability loses to A's higher-priority Stackable).
        $result = app(ApplyDiscountsToCartAction::class)->execute($tenantId, $agentId, $cartId);
        $this->assertCount(2, $result['appliedDiscounts']);
        $appliedRuleIds = array_map(fn ($applied) => $applied->discountRuleId, $result['appliedDiscounts']);
        $this->assertContains($ruleA->id, $appliedRuleIds);
        $this->assertContains($ruleC->id, $appliedRuleIds);
        $this->assertNotContains($ruleB->id, $appliedRuleIds);

        // Cart-level usage never touches DiscountRule.usedCount.
        $refetchedA = app(GetDiscountRuleAction::class)->execute($ruleA->id, $tenantId);
        $refetchedC = app(GetDiscountRuleAction::class)->execute($ruleC->id, $tenantId);
        $this->assertSame(0, $refetchedA->usedCount);
        $this->assertSame(0, $refetchedC->usedCount);
    }

    public function test_applyDiscounts_calledTwice_replacesRatherThanAppends(): void
    {
        [$tenantId, $agentId] = $this->registerAgent();
        $cartId = $this->buildCartWithItems($tenantId, $agentId, quantity: 4, unitPriceAmount: 1000);

        app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: '10% off',
            discountType: 'percentage',
            discountValue: 10,
            priority: 10,
            stackability: 'stackable',
        );

        app(ApplyDiscountsToCartAction::class)->execute($tenantId, $agentId, $cartId);
        $afterFirst = app(AppliedDiscountRepositoryInterface::class)->listByCart($cartId, $tenantId);
        $this->assertCount(1, $afterFirst);

        // Add a second, differently-shaped rule and re-apply — the set must be replaced, not appended to.
        app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: '$1 off',
            discountType: 'fixed_amount',
            discountValue: 100,
            priority: 20,
            stackability: 'stackable',
        );

        app(ApplyDiscountsToCartAction::class)->execute($tenantId, $agentId, $cartId);
        $afterSecond = app(AppliedDiscountRepositoryInterface::class)->listByCart($cartId, $tenantId);
        $this->assertCount(2, $afterSecond);
    }

    public function test_expiredRule_neverAppearsInAvailableOrApply(): void
    {
        [$tenantId, $agentId] = $this->registerAgent();
        $cartId = $this->buildCartWithItems($tenantId, $agentId, quantity: 4, unitPriceAmount: 1000);

        app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: 'Expired 10% off',
            discountType: 'percentage',
            discountValue: 10,
            priority: 10,
            stackability: 'stackable',
            startsAt: '2020-01-01T00:00:00+00:00',
            expiresAt: '2020-06-01T00:00:00+00:00',
        );

        $available = app(GetAvailableDiscountsAction::class)->execute($tenantId, $agentId, $cartId);
        $this->assertCount(0, $available);

        $result = app(ApplyDiscountsToCartAction::class)->execute($tenantId, $agentId, $cartId);
        $this->assertCount(0, $result['appliedDiscounts']);
        $this->assertSame(0, $result['totalDiscountAmount']);
    }

    public function test_tenantIsolation_ruleFromOtherTenantNeverEvaluatedAgainstThisTenantsCart(): void
    {
        [$tenantA, $agentA] = $this->registerAgent();
        [$tenantB] = $this->registerAgent();

        $cartId = $this->buildCartWithItems($tenantA, $agentA, quantity: 4, unitPriceAmount: 1000);

        // Rule created under tenant B must never affect tenant A's cart.
        app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantB,
            name: '10% off (tenant B only)',
            discountType: 'percentage',
            discountValue: 10,
            priority: 10,
            stackability: 'stackable',
        );

        $available = app(GetAvailableDiscountsAction::class)->execute($tenantA, $agentA, $cartId);
        $this->assertCount(0, $available);

        $result = app(ApplyDiscountsToCartAction::class)->execute($tenantA, $agentA, $cartId);
        $this->assertCount(0, $result['appliedDiscounts']);
    }

    public function test_emptyCart_available_returnsEmptyArray(): void
    {
        [$tenantId, $agentId] = $this->registerAgent();

        $cart = app(CartRepositoryInterface::class)->save(
            Cart::open($tenantId, MemberType::Agent, $agentId),
        );

        app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: '10% off',
            discountType: 'percentage',
            discountValue: 10,
            priority: 10,
            stackability: 'stackable',
        );

        $available = app(GetAvailableDiscountsAction::class)->execute($tenantId, $agentId, $cart->id());
        $this->assertSame([], $available);
    }

    public function test_wrongOwner_throwsCartNotFound(): void
    {
        [$tenantId, $agentId] = $this->registerAgent();
        [, $otherAgentId] = $this->registerAgent($tenantId);

        $cartId = $this->buildCartWithItems($tenantId, $agentId, quantity: 2, unitPriceAmount: 1000);

        $this->expectException(CartNotFoundException::class);
        app(GetAvailableDiscountsAction::class)->execute($tenantId, $otherAgentId, $cartId);
    }

    /**
     * @return array{0: int, 1: int} [tenantId, agentId]
     */
    private function registerAgent(?int $tenantId = null): array
    {
        $tenantId ??= app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid())->id;
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return [$tenantId, $agent->id];
    }

    private function buildCartWithItems(int $tenantId, int $agentId, int $quantity, int $unitPriceAmount): int
    {
        $product = app(CreateProductAction::class)->execute(
            $tenantId,
            'Widget',
            'WIDGET-'.uniqid(),
            $unitPriceAmount,
            'USD',
            status: 'active',
        );

        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, $quantity + 10));

        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $product->id, $quantity);

        return $cart->id;
    }
}
