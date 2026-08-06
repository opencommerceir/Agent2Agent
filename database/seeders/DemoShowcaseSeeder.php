<?php

namespace Database\Seeders;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Application\DTOs\AgentData;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Domain\ValueObjects\Language;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\AgentOrchestrator\Application\Actions\ExecuteGoalAction;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType as PersonaType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCategoryAction;
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\CreateProductVariantAction;
use App\Modules\Commerce\Application\Actions\CreateVariantAttributeAction;
use App\Modules\Commerce\Application\Actions\CreateWarehouseAction;
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Application\DTOs\ProductData;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Models\Order as OrderModel;
use App\Modules\Commerce\Infrastructure\Models\OrderItem as OrderItemModel;
use App\Modules\Commerce\Infrastructure\Models\Payment as PaymentModel;
use App\Modules\CRM\Application\Actions\CreateTicketAction;
use App\Modules\Loyalty\Application\Actions\EarnPointsAction;
use App\Modules\Notifications\Application\Actions\CreateTemplateAction;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Seeds a single, well-known Tenant (slug: self::TENANT_SLUG) that backs
 * the `/showcase` chat UI (Interfaces layer, see
 * app/Http/Controllers/Showcase/ShowcaseController.php) — a realistic-
 * looking store an Agent persona can plan and execute real Goals against,
 * rather than an empty test fixture.
 *
 * Deliberately **not** called from DatabaseSeeder::run() — this is a
 * demo-only fixture an operator opts into explicitly (`php artisan
 * db:seed --class=DemoShowcaseSeeder` or `php artisan demo:reset`), not
 * part of every environment's default seed. Idempotent by construction:
 * if a Tenant with this slug already exists, run() returns immediately
 * without touching anything (ResetDemoShowcaseCommand is what wipes it
 * first when a fresh reseed is actually wanted).
 *
 * Every write below goes through the exact same Actions any real MCP
 * caller/Dashboard controller already uses — this seeder holds no
 * business logic of its own, only realistic call sequences and data
 * choices (HANDOFF §3 pattern #19's "consume existing Actions, never
 * reimplement the logic" rule, applied to a seeder instead of a
 * Controller). The one deliberate exception is Order/Payment timestamp
 * backdating (see seedOrders() below) — no Action in this codebase can
 * place a historically-dated Order (nor should one exist for real
 * business use), so this seeder reaches the Eloquent models directly for
 * that one, purely cosmetic, seed-only concern.
 */
class DemoShowcaseSeeder extends Seeder
{
    public const TENANT_SLUG = 'demo-showcase';

    public const DEMO_AGENT_NAME = 'Demo Agent';

    /**
     * The exact permission set proven sufficient, end to end, for every
     * one of the 4 shipped Agent personas (config/agents/{ceo,sales,
     * support,finance}.php) — the identical list
     * tests/Feature/AgentOrchestrator/GoalExecutionTest.php's own
     * REQUIRED_PERMISSIONS already established and tests against.
     */
    private const PERMISSIONS = [
        'agent.goals.execute',
        'agent.executions.read',
        'reporting.sales.read',
        'reporting.revenue.read',
        'analytics.kpis.read',
        'commerce.coupons.create',
        'notifications.messages.send',
        'notifications.templates.manage',
        'crm.tickets.read',
        'finance.invoices.read',
        // Showcase prep, Phase 2 — needed for config/agents/ceo.php's own
        // new `delegate` planning rule (agent.collaboration.delegate) to
        // actually succeed live, not just plan then fail its one step.
        'agent.collaboration.delegate',
        'agent.collaboration.read',
    ];

    /**
     * 5 categories x 8 products = 40 (within the requested 30-50 range).
     * Price is cents, matching Money's own smallest-currency-unit
     * convention (HANDOFF gotcha #4).
     */
    private const CATALOG = [
        'Electronics' => [
            ['Wireless Bluetooth Headphones', 8999],
            ['Smart Fitness Watch', 12999],
            ['4K Action Camera', 15999],
            ['Portable Power Bank 20000mAh', 4999],
            ['Mechanical Gaming Keyboard', 7999],
            ['Wireless Mouse', 2999],
            ['USB-C Hub Adapter', 3499],
            ['Noise Cancelling Earbuds', 9999],
        ],
        'Apparel' => [
            ["Men's Classic Denim Jacket", 6999],
            ["Women's Yoga Leggings", 3999],
            ['Unisex Cotton Hoodie', 4499],
            ['Running Sneakers', 8499],
            ['Merino Wool Sweater', 7499],
            ['Slim Fit Chino Pants', 5499],
            ['Graphic Print T-Shirt', 2499],
            ['Waterproof Rain Jacket', 9499],
        ],
        'Home & Kitchen' => [
            ['Stainless Steel Cookware Set', 14999],
            ['Electric Kettle 1.7L', 3999],
            ['Ceramic Coffee Mug Set', 2999],
            ['Non-Stick Frying Pan', 3499],
            ['Memory Foam Pillow', 4999],
            ['Bamboo Cutting Board', 2499],
            ['Air Fryer 5L', 11999],
            ['Cotton Bed Sheet Set', 5999],
        ],
        'Beauty' => [
            ['Vitamin C Facial Serum', 3299],
            ['Hydrating Face Moisturizer', 2799],
            ['Matte Lipstick Set', 3999],
            ['Argan Oil Hair Treatment', 2499],
            ['Sunscreen SPF50', 1999],
            ['Charcoal Face Mask', 1799],
            ['Rose Water Toner', 1599],
            ['Nail Polish Collection', 2999],
        ],
        'Sports' => [
            ['Yoga Mat Premium', 3499],
            ['Adjustable Dumbbell Set', 12999],
            ['Resistance Bands Set', 1999],
            ['Insulated Water Bottle', 2499],
            ['Running Shorts', 2999],
            ['Camping Tent 4-Person', 18999],
            ['Cycling Helmet', 5999],
            ['Foam Roller', 2799],
        ],
    ];

    /** Product name => [attribute name, list of values]. */
    private const VARIANT_PRODUCTS = [
        'Unisex Cotton Hoodie' => ['Size', ['S', 'M', 'L', 'XL']],
        'Running Sneakers' => ['Size', ['8', '9', '10']],
        'Graphic Print T-Shirt' => ['Size', ['S', 'M', 'L']],
        'Wireless Bluetooth Headphones' => ['Color', ['Black', 'White']],
        'Yoga Mat Premium' => ['Color', ['Purple', 'Blue']],
        'Insulated Water Bottle' => ['Color', ['Silver', 'Black']],
    ];

    private const CUSTOMER_COUNT = 40;

    private const ORDER_COUNT = 180;

    private const ORDER_SPREAD_DAYS = 85;

    /** @var list<ProductData> */
    private array $products = [];

    /**
     * Indexes into $products that are safe for seedOrders() to pick from
     * — every deliberately low-stock product (2-8 units, see
     * seedCatalog()) is excluded here so 180 randomly-generated historical
     * orders can never exhaust one and cascade into skipped orders; a
     * low-stock product still exists and is still visibly low-stock, it's
     * just never the one this seeder's own Order history happens to sell.
     *
     * @var list<int>
     */
    private array $orderableProductIndexes = [];

    /** @var list<int> */
    private array $customerIds = [];

    private FakerGenerator $faker;

    public function run(): void
    {
        $tenants = app(TenantRepositoryInterface::class);

        if ($tenants->findBySlug(self::TENANT_SLUG) !== null) {
            $this->command?->info('Demo showcase tenant already exists — skipping (run `php artisan demo:reset` to rebuild it).');

            return;
        }

        $this->faker = FakerFactory::create();

        $this->seedCapabilities();

        [$tenantId, $agent] = $this->seedIdentity();

        $this->seedNotificationTemplate($tenantId);
        [$warehouseEastId, $warehouseWestId] = $this->seedWarehouses($tenantId);
        $this->seedCatalog($tenantId, $warehouseEastId, $warehouseWestId);
        $this->seedVariants($tenantId);
        $this->seedCustomers($tenantId);
        $this->seedLoyalty($tenantId);
        $this->seedTickets($tenantId, $agent->id);
        $couponCode = $this->seedDiscounts($tenantId);
        $this->seedOrders($tenantId, $agent->id, $couponCode);
        $this->seedExecutions($agent);

        $this->command?->info('Demo showcase tenant seeded: '.self::TENANT_SLUG);
    }

    private function seedCapabilities(): void
    {
        $this->call(CommerceCapabilitiesSeeder::class);
        $this->call(ReportingCapabilitiesSeeder::class);
        $this->call(AnalyticsCapabilitiesSeeder::class);
        $this->call(NotificationsCapabilitiesSeeder::class);
        $this->call(CRMCapabilitiesSeeder::class);
        $this->call(FinanceCapabilitiesSeeder::class);
        $this->call(AgentOrchestratorCapabilitiesSeeder::class);
    }

    /**
     * @return array{0: int, 1: AgentData}
     */
    private function seedIdentity(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Demo Showcase Store', self::TENANT_SLUG);
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Demo Showcase HQ', self::TENANT_SLUG.'-hq');
        $agentData = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, self::DEMO_AGENT_NAME, 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agentData->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Showcase Operator', self::TENANT_SLUG.'-operator');

        foreach (self::PERMISSIONS as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agentData->id, $role->id);

        return [$tenant->id, $agentData];
    }

    /**
     * Without this, config/agents/{ceo,sales}.php's own `sales`/`default`/
     * `promotion`/`campaign` planning rules all end on a
     * notification.message.send step that fails silently for lack of an
     * active Template — the exact gotcha
     * tests/Feature/AgentOrchestrator/GoalExecutionTest.php's own
     * seedPromotionTemplate() already documents and guards against.
     */
    private function seedNotificationTemplate(int $tenantId): void
    {
        app(CreateTemplateAction::class)->execute(
            tenantId: $tenantId,
            type: 'promotion_announcement',
            channelType: 'email',
            subjectTemplate: '{{discount_percent}}% off this week at Demo Showcase Store',
            bodyTemplate: 'Enjoy {{discount_percent}}% off your next order — use the code we just generated.',
        );
    }

    /**
     * @return array{0: int, 1: int} [east warehouse id, west warehouse id]
     */
    private function seedWarehouses(int $tenantId): array
    {
        $east = app(CreateWarehouseAction::class)->execute(
            $tenantId,
            'WH-EAST1',
            'East Coast Fulfillment Center',
            40.7128,
            -74.0060,
            '123 Warehouse Ave, New York, NY',
        );

        $west = app(CreateWarehouseAction::class)->execute(
            $tenantId,
            'WH-WEST1',
            'West Coast Fulfillment Center',
            34.0522,
            -118.2437,
            '456 Storage Blvd, Los Angeles, CA',
        );

        return [$east->id, $west->id];
    }

    private function seedCatalog(int $tenantId, int $warehouseEastId, int $warehouseWestId): void
    {
        $inventories = app(InventoryRepositoryInterface::class);
        $skuCounter = 0;

        foreach (self::CATALOG as $categoryName => $catalogProducts) {
            $category = app(CreateCategoryAction::class)->execute($tenantId, $categoryName);

            foreach ($catalogProducts as [$name, $priceCents]) {
                $skuCounter++;
                $sku = sprintf('DEMO-%03d', $skuCounter);

                $product = app(CreateProductAction::class)->execute(
                    tenantId: $tenantId,
                    name: $name,
                    sku: $sku,
                    priceAmount: $priceCents,
                    priceCurrency: 'USD',
                    categoryId: $category->id,
                    description: "{$name} — part of the Demo Showcase Store catalog.",
                    status: 'active',
                );

                $this->products[] = $product;
                $productIndex = count($this->products) - 1;

                // Every 7th product is deliberately low-stock, so
                // Analytics' own Low Stock KPI and the showcase have
                // something real to surface, not just healthy numbers —
                // excluded from seedOrders()'s own random pool (see
                // $orderableProductIndexes's own docblock) so it stays
                // low-stock instead of being exhausted by chance.
                $isLowStock = $skuCounter % 7 === 0;
                $defaultQty = $isLowStock ? random_int(2, 8) : random_int(150, 400);
                $inventories->save(Inventory::stock($tenantId, $product->id, $defaultQty));

                if (! $isLowStock) {
                    $this->orderableProductIndexes[] = $productIndex;
                }

                // Half the catalog also carries warehouse-scoped stock,
                // independent of the default row above (AddToCartAction/
                // PlaceOrderAction only ever read the default,
                // warehouse_id-null row — see Inventory's own docblock —
                // so this never affects whether an order can be placed).
                if ($skuCounter % 2 === 0) {
                    $inventories->save(Inventory::stock($tenantId, $product->id, random_int(0, 60), null, $warehouseEastId));
                    $inventories->save(Inventory::stock($tenantId, $product->id, random_int(0, 60), null, $warehouseWestId));
                }
            }
        }
    }

    private function seedVariants(int $tenantId): void
    {
        $productsByName = [];
        foreach ($this->products as $product) {
            $productsByName[$product->name] = $product;
        }

        // VariantAttribute is a tenant-wide registry (unique(tenant_id,
        // name)), not a per-product one — several catalog products below
        // share the same attribute *name* ("Size") with different value
        // sets, so each distinct name is registered only once, the first
        // time it's seen. CreateProductVariantAction's own `attributes`
        // input is free-form regardless (its own docblock: no
        // registry-level check against VariantAttribute/Value rows), so
        // this registry row only backs `commerce.attribute.list`'s own
        // listing, never variant creation itself.
        $registeredAttributes = [];

        foreach (self::VARIANT_PRODUCTS as $productName => [$attributeName, $values]) {
            $product = $productsByName[$productName] ?? null;

            if ($product === null) {
                continue;
            }

            if (! in_array($attributeName, $registeredAttributes, true)) {
                app(CreateVariantAttributeAction::class)->execute($tenantId, $attributeName, $values);
                $registeredAttributes[] = $attributeName;
            }

            foreach ($values as $value) {
                app(CreateProductVariantAction::class)->execute(
                    tenantId: $tenantId,
                    productId: $product->id,
                    attributes: [$attributeName => $value],
                    priceAmount: $product->priceAmount,
                    priceCurrency: 'USD',
                    initialStock: random_int(20, 100),
                );
            }
        }
    }

    private function seedCustomers(int $tenantId): void
    {
        for ($i = 0; $i < self::CUSTOMER_COUNT; $i++) {
            $customer = app(CreateCustomerAction::class)->execute(
                tenantId: $tenantId,
                firstName: $this->faker->firstName(),
                lastName: $this->faker->lastName(),
                email: $this->faker->unique()->safeEmail(),
                phone: $this->faker->numerify('+1##########'),
            );

            $this->customerIds[] = $customer->id;
        }
    }

    /**
     * ~40% of customers get a LoyaltyAccount with a real earn transaction
     * — enough for `loyalty.account.get`/`.transaction.list`-shaped
     * exploration to show real, varied balances, not a uniform demo value.
     */
    private function seedLoyalty(int $tenantId): void
    {
        $earnAction = app(EarnPointsAction::class);

        foreach ($this->customerIds as $index => $customerId) {
            if ($index % 5 >= 2) {
                continue;
            }

            $earnAction->execute(
                tenantId: $tenantId,
                customerId: $customerId,
                points: random_int(50, 500),
                description: 'Demo showcase seed — welcome bonus',
            );
        }
    }

    private function seedTickets(int $tenantId, int $agentId): void
    {
        $subjects = [
            ['Order has not arrived yet', 'high'],
            ['Wrong item received', 'urgent'],
            ['How do I return this product?', 'medium'],
            ['Question about warranty coverage', 'low'],
            ['Discount code not applying at checkout', 'medium'],
            ['Product arrived damaged', 'high'],
            ['Need to change shipping address', 'medium'],
            ['Missing item from my order', 'high'],
            ['Request for a bulk order quote', 'low'],
            ['Account login issue', 'medium'],
        ];

        foreach ($subjects as $index => [$subject, $priority]) {
            $customerId = $this->customerIds[$index % count($this->customerIds)];

            app(CreateTicketAction::class)->execute(
                tenantId: $tenantId,
                agentId: $agentId,
                customerId: $customerId,
                subject: $subject,
                description: "Demo showcase seed ticket: {$subject}",
                priority: $priority,
            );
        }
    }

    /**
     * Returns one safe-to-use standalone coupon code (no minimum order
     * amount, generous max uses) that seedOrders() applies to a handful
     * of orders — plus one DiscountRule-linked coupon and one
     * conditional DiscountRule, both left unused by any seeded order, so
     * `commerce.discount.available`/`commerce.rule.list` have real,
     * currently-active rules to show even before an Agent goal touches
     * discounts at all.
     */
    private function seedDiscounts(int $tenantId): string
    {
        app(CreateCouponAction::class)->execute(
            tenantId: $tenantId,
            code: 'COUPON-SAVE5',
            discountType: 'percentage',
            discountValue: 5,
            maxUses: 1000,
        );

        $rule = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: 'Big Order Discount',
            discountType: 'fixed_amount',
            discountValue: 1000,
            priority: 5,
            stackability: 'exclusive',
            conditions: [['type' => 'min_subtotal', 'value' => 5000]],
            description: '$10 off orders over $50',
        );

        app(CreateCouponAction::class)->execute(
            tenantId: $tenantId,
            code: 'COUPON-VIP10',
            discountType: 'percentage',
            discountValue: 10,
            maxUses: 200,
            discountRuleId: $rule->id,
        );

        app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantId,
            name: 'Everyday Stackable 10%',
            discountType: 'percentage',
            discountValue: 10,
            priority: 10,
            stackability: 'stackable',
        );

        return 'COUPON-SAVE5';
    }

    /**
     * Places ORDER_COUNT real Cart -> Payment -> Order checkouts through
     * AddToCartAction/ProcessPaymentAction (the exact flow
     * `commerce.checkout.process` itself uses), then backdates each
     * placed Order/Payment/OrderItem's own created_at/updated_at into a
     * random moment across the last ORDER_SPREAD_DAYS days — the one
     * place this seeder reaches Eloquent models directly rather than an
     * Action, since no Action in this codebase places a historically-
     * dated Order (nor should a real business operation ever backdate
     * one). Reporting's/Analytics' own Query Builders both group by
     * `orders.created_at` (confirmed against SalesQueryBuilder), so this
     * is what actually makes the Dashboard/showcase's sales trend chart
     * show real day-to-day variance instead of a single flat spike today.
     */
    private function seedOrders(int $tenantId, int $agentId, string $safeCouponCode): void
    {
        $addToCart = app(AddToCartAction::class);
        $processPayment = app(ProcessPaymentAction::class);
        $orderableCount = count($this->orderableProductIndexes);

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $itemCount = random_int(1, 3);
            // array_rand() returns keys into $orderableProductIndexes, not
            // the product indexes themselves — map back through the array
            // to get real indexes into $this->products.
            $pickedKeys = (array) array_rand($this->orderableProductIndexes, min($itemCount, $orderableCount));
            $pickedIndexes = array_map(fn (int $key) => $this->orderableProductIndexes[$key], $pickedKeys);

            try {
                foreach ($pickedIndexes as $productIndex) {
                    $product = $this->products[$productIndex];
                    $addToCart->execute(
                        tenantId: $tenantId,
                        ownerType: MemberType::Agent,
                        ownerId: $agentId,
                        productId: $product->id,
                        quantity: random_int(1, 3),
                    );
                }

                $cart = app(CartRepositoryInterface::class)
                    ->findActiveByOwner($tenantId, MemberType::Agent, $agentId);

                $result = $processPayment->execute(
                    tenantId: $tenantId,
                    agentId: $agentId,
                    cartId: $cart->id(),
                    paymentMethod: 'credit_card',
                    paymentDetails: [],
                    couponCode: $i < 15 ? $safeCouponCode : null,
                    customerId: $this->customerIds[array_rand($this->customerIds)],
                );

                $this->backdateOrder($result['order']->id, $this->randomPastMoment());
            } catch (Throwable) {
                // A handful of orders skipping (e.g. a coupon max-use edge
                // case) doesn't need to abort 179 other, otherwise-good
                // orders — this is seed data richness, not a correctness
                // guarantee any real capability depends on.
                continue;
            }
        }
    }

    private function randomPastMoment(): Carbon
    {
        return Carbon::now()
            ->subDays(random_int(0, self::ORDER_SPREAD_DAYS))
            ->subHours(random_int(0, 23))
            ->subMinutes(random_int(0, 59));
    }

    private function backdateOrder(int $orderId, Carbon $when): void
    {
        OrderModel::where('id', $orderId)->update(['created_at' => $when, 'updated_at' => $when]);
        PaymentModel::where('order_id', $orderId)->update(['created_at' => $when, 'updated_at' => $when]);
        OrderItemModel::where('order_id', $orderId)->update(['created_at' => $when, 'updated_at' => $when]);
    }

    /**
     * Pre-runs 3 real Goals through the unmodified ExecuteGoalAction
     * (never a raw DB insert — HANDOFF's own recurring "consume the real
     * Action, don't fake its output" rule) so Execution Memory has
     * something real to have already learned from the moment a visitor's
     * very first showcase chat message arrives, and so
     * `agent.execution.list`/`agent.memory.insights` aren't empty either.
     */
    private function seedExecutions(AgentData $agent): void
    {
        $action = app(ExecuteGoalAction::class);
        $context = AuthContext::forAgent($agent, Language::English);

        $goals = [
            [PersonaType::Ceo, 'Increase sales by 15% this week'],
            [PersonaType::Support, 'Review open support tickets'],
            [PersonaType::Finance, 'Review finance and revenue'],
        ];

        foreach ($goals as [$personaType, $goalText]) {
            try {
                $action->execute($goalText, $personaType, $context);
            } catch (Throwable) {
                // Seed-time execution failures (e.g. a transient plan
                // step issue) shouldn't abort the rest of the seeder —
                // the showcase chat UI can still produce this same
                // execution live once a visitor actually sends the goal.
                continue;
            }
        }
    }
}
