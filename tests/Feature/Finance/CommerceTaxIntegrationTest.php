<?php

namespace Tests\Feature\Finance;

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
use App\Modules\Finance\Application\Actions\CreateTaxRateAction;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the Phase 3.2 integration point end to end: Commerce's own
 * `commerce.checkout.calculate` capability, completely unaware of
 * Finance's existence at the class level, gets real per-tenant tax rates
 * through TaxRateProviderInterface -> CommerceTaxRateProvider once the
 * Finance module is loaded (which it always is, per
 * bootstrap/providers.php) — and falls back to the exact pre-Finance 9%
 * behavior when no TaxRate is configured, so every Stage-5 checkout test
 * that predates this module keeps passing unchanged.
 */
class CommerceTaxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculatePricing_withRegionSpecificTaxRateConfigured_usesIt(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        [$tenantId, $token] = $this->registerAgentWithPermissions(['commerce.cart.manage', 'commerce.checkout.read']);

        app(CreateTaxRateAction::class)->execute($tenantId, 'US-CA', 850); // 8.5%

        $cartId = $this->addWidgetToCart($tenantId, $token);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId, 'region' => 'US-CA'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.pricing.subtotalAmount', 10000);
        $response->assertJsonPath('data.pricing.taxAmount', 850); // not the hardcoded 900 (9%)
        $response->assertJsonPath('data.pricing.totalAmount', 10850);
    }

    public function test_calculatePricing_withTenantDefaultTaxRateConfigured_usesItWhenNoRegionGiven(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        [$tenantId, $token] = $this->registerAgentWithPermissions(['commerce.cart.manage', 'commerce.checkout.read']);

        app(CreateTaxRateAction::class)->execute($tenantId, 'DEFAULT', 500); // 5%

        $cartId = $this->addWidgetToCart($tenantId, $token);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.pricing.taxAmount', 500);
    }

    public function test_calculatePricing_withNoTaxRateConfiguredAtAll_fallsBackToHardcodedNinePercent(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        [$tenantId, $token] = $this->registerAgentWithPermissions(['commerce.cart.manage', 'commerce.checkout.read']);

        $cartId = $this->addWidgetToCart($tenantId, $token);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.pricing.taxAmount', 900); // unchanged pre-Finance behavior
    }

    public function test_calculatePricing_withInactiveRegionTaxRate_fallsBackToTenantDefault(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        [$tenantId, $token] = $this->registerAgentWithPermissions(['commerce.cart.manage', 'commerce.checkout.read']);

        $inactive = app(CreateTaxRateAction::class)->execute($tenantId, 'US-CA', 850);
        app(\App\Modules\Finance\Application\Actions\UpdateTaxRateAction::class)->execute($inactive->id, $tenantId, 850, false);
        app(CreateTaxRateAction::class)->execute($tenantId, 'DEFAULT', 500);

        $cartId = $this->addWidgetToCart($tenantId, $token);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId, 'region' => 'US-CA'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.pricing.taxAmount', 500); // US-CA inactive -> DEFAULT wins
    }

    private function addWidgetToCart(int $tenantId, string $token): int
    {
        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 10000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, 10));

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 1],
        ], ['Authorization' => "Bearer {$token}"]);

        return $response->json('data.cart.id');
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
