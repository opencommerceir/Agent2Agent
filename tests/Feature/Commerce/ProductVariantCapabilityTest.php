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
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from Phase 5, Stage 1's own request
 * (§7.21): create a Product -> create 2 variant attributes -> generate
 * every combination -> set a real price/stock per variant -> add one
 * specific variant to Cart -> place an Order -> confirm the variant's
 * own Inventory (not the parent Product's) actually moved -> confirm a
 * duplicate combination is rejected -> confirm tenant isolation.
 */
class ProductVariantCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullVariantLifecycle_fromAttributesToPlacedOrder(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantId, $token] = $this->registerAgentWithPermissions([
            'commerce.attributes.manage', 'commerce.attributes.read',
            'commerce.variants.manage', 'commerce.variants.read',
            'commerce.cart.manage', 'commerce.cart.read',
            'commerce.orders.create',
        ]);

        $product = app(CreateProductAction::class)->execute($tenantId, 'T-Shirt', 'TSHIRT', 2999, 'USD', status: 'active');

        // Step 2: create the two variant attributes.
        $colorResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.attribute.create',
            'input' => ['name' => 'Color', 'values' => ['Red', 'Blue', 'Black']],
        ], ['Authorization' => "Bearer {$token}"]);
        $colorResponse->assertStatus(200);
        $colorAttributeId = $colorResponse->json('data.attribute.id');

        $sizeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.attribute.create',
            'input' => ['name' => 'Size', 'values' => ['S', 'M', 'L']],
        ], ['Authorization' => "Bearer {$token}"]);
        $sizeResponse->assertStatus(200);
        $sizeAttributeId = $sizeResponse->json('data.attribute.id');

        $listAttributesResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.attribute.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);
        $listAttributesResponse->assertStatus(200);
        $this->assertCount(2, $listAttributesResponse->json('data.attributes'));

        // Step 3: generate every combination (3 x 3 = 9).
        $generateResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.variant.generate',
            'input' => [
                'product_id' => $product->id,
                'attribute_ids' => [$colorAttributeId, $sizeAttributeId],
                'price_amount' => 2999,
                'price_currency' => 'USD',
            ],
        ], ['Authorization' => "Bearer {$token}"]);

        $generateResponse->assertStatus(200);
        $generateResponse->assertJsonPath('data.count', 9);

        // Step 4: confirm the SKUs match the expected PARENT-ATTR1-ATTR2 shape.
        $skus = collect($generateResponse->json('data.variants'))->pluck('sku');
        $this->assertTrue($skus->contains('TSHIRT-RED-S'));
        $this->assertTrue($skus->contains('TSHIRT-RED-L'));
        $this->assertTrue($skus->contains('TSHIRT-BLACK-L'));
        $this->assertCount(9, $skus->unique());

        $redLVariant = collect($generateResponse->json('data.variants'))->firstWhere('sku', 'TSHIRT-RED-L');
        $redLVariantId = $redLVariant['id'];

        // Regenerating is idempotent — no new variants, none of the 9 duplicated.
        $regenerateResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.variant.generate',
            'input' => [
                'product_id' => $product->id,
                'attribute_ids' => [$colorAttributeId, $sizeAttributeId],
                'price_amount' => 2999,
                'price_currency' => 'USD',
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $regenerateResponse->assertJsonPath('data.count', 0);

        // Step 5: set a real price and stock for TSHIRT-RED-L specifically.
        $updateResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.variant.update',
            'input' => [
                'variant_id' => $redLVariantId,
                'price_amount' => 3499,
                'price_currency' => 'USD',
                'is_active' => true,
                'stock_quantity' => 20,
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('data.variant.priceAmount', 3499);
        $updateResponse->assertJsonPath('data.variant.quantityOnHand', 20);

        // Step 6: add TSHIRT-RED-L to the cart.
        $addResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'variant_id' => $redLVariantId, 'quantity' => 2],
        ], ['Authorization' => "Bearer {$token}"]);
        $addResponse->assertStatus(200);
        $cartId = $addResponse->json('data.cart.id');

        // Step 7: confirm the cart line carries the variant, priced at the variant's own price (3499), not the parent's (2999).
        $getCartResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.get',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);
        $getCartResponse->assertJsonPath('data.cart.items.0.variantId', $redLVariantId);
        $getCartResponse->assertJsonPath('data.cart.items.0.priceAmount', 3499);

        // Step 8: place the order.
        $placeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.place',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$token}"]);
        $placeResponse->assertStatus(200);
        $placeResponse->assertJsonPath('data.order.items.0.variantId', $redLVariantId);

        // Step 9: the variant's own Inventory dropped from 20 to 18; the
        // parent Product's own inventory (variant_id null) never existed
        // and stays untouched.
        $variantInventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenantId, $redLVariantId);
        $this->assertSame(18, $variantInventory->quantityOnHand());

        $parentInventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenantId);
        $this->assertNull($parentInventory);

        // Product is now correctly flagged as having variants.
        $this->assertTrue(app(ProductRepositoryInterface::class)->findById($product->id, $tenantId)->isParent());

        // Step 10: creating the exact same combination again is rejected.
        $duplicateResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.variant.create',
            'input' => [
                'product_id' => $product->id,
                'attributes' => ['Color' => 'Red', 'Size' => 'L'],
                'price_amount' => 3499,
                'price_currency' => 'USD',
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $duplicateResponse->assertStatus(409);
        $duplicateResponse->assertJsonPath('error.code', 'CONFLICT');
    }

    public function test_variantGet_isIsolatedByTenant(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantA, $tokenA] = $this->registerAgentWithPermissions(['commerce.variants.manage', 'commerce.variants.read']);
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.variants.read']);

        $product = app(CreateProductAction::class)->execute($tenantA, 'T-Shirt', 'TSHIRT', 2999, 'USD', status: 'active');

        $createResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.variant.create',
            'input' => [
                'product_id' => $product->id,
                'attributes' => ['Color' => 'Red'],
                'price_amount' => 2999,
                'price_currency' => 'USD',
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $variantId = $createResponse->json('data.variant.id');

        $crossTenantResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.variant.get',
            'input' => ['variant_id' => $variantId],
        ], ['Authorization' => "Bearer {$tokenB}"]);

        $crossTenantResponse->assertStatus(404);
        $crossTenantResponse->assertJsonPath('error.code', 'NOT_FOUND');
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

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Shopper', 'shopper-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}
