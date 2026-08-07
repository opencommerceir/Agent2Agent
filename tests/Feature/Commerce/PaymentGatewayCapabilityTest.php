<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real, redirect-based checkout flow end to end, entirely over MCP
 * (§7.37) — `commerce.payment.initiate` -> `commerce.payment.confirm` ->
 * a real Order/Payment, plus `commerce.payment.inquiry` and tenant
 * isolation. Uses the `mock` gateway (the phpunit.xml default,
 * `PAYMENT_GATEWAY=mock`) rather than a live Zibal/Stripe call, the
 * identical "no real network access from the test suite" discipline
 * every other gateway/Connector test in this codebase already follows.
 */
class PaymentGatewayCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullRedirectPaymentScenario(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantA, $tokenA] = $this->registerAgentWithPermissions([
            'commerce.cart.manage', 'commerce.checkout.read', 'commerce.checkout.create',
        ]);
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.checkout.create', 'commerce.checkout.read']);

        $product = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'WIDGET-1', 10000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 10));

        $addToCart = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 1],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $addToCart->assertStatus(200);
        $cartId = $addToCart->json('data.cart.id');

        // Step 1: initiate — a real redirect URL and an opaque tracking
        // reference, no Order/Payment yet.
        $initiate = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.payment.initiate',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $initiate->assertStatus(200);
        $initiate->assertJsonPath('data.gateway', 'mock');
        $this->assertNotEmpty($initiate->json('data.redirect_url'));
        $trackingReference = $initiate->json('data.tracking_reference');
        $this->assertIsInt($trackingReference);
        $this->assertDatabaseCount('orders', 0);

        // Cross-tenant confirm must 404, never reach Tenant A's session.
        $crossTenantConfirm = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.payment.confirm',
            'input' => ['tracking_reference' => $trackingReference],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantConfirm->assertStatus(404);
        $crossTenantConfirm->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 2: confirm — the mock gateway's own verify() succeeds,
        // finalizing a real Order + Payment.
        $confirm = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.payment.confirm',
            'input' => ['tracking_reference' => $trackingReference],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $confirm->assertStatus(200);
        $confirm->assertJsonPath('data.successful', true);
        $confirm->assertJsonPath('data.order.status', 'confirmed');
        $confirm->assertJsonPath('data.order.totalAmount', 10900);
        $confirm->assertJsonPath('data.payment.status', 'completed');
        $confirm->assertJsonPath('data.payment.gateway', 'mock');

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenantA);
        $this->assertSame(9, $inventory->quantityOnHand());

        // Step 3: inquiry — read-only, reflects the now-completed session.
        $inquiry = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.payment.inquiry',
            'input' => ['tracking_reference' => $trackingReference],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $inquiry->assertStatus(200);
        $inquiry->assertJsonPath('data.session_status', 'completed');

        // Tenant B can never inquire into Tenant A's session either.
        $crossTenantInquiry = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.payment.inquiry',
            'input' => ['tracking_reference' => $trackingReference],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantInquiry->assertStatus(404);
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Shopper', 'shopper-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}
