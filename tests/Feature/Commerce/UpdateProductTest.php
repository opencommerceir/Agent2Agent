<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\UpdateProductAction;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_updatesNameStatusAndPriceButNotSku(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD');

        $result = app(UpdateProductAction::class)->execute(
            id: $product->id,
            tenantId: $tenant->id,
            name: 'Widget Pro',
            description: 'Now with more widget.',
            priceAmount: 2999,
            priceCurrency: 'USD',
            status: 'active',
        );

        $this->assertSame('Widget Pro', $result->name);
        $this->assertSame(2999, $result->priceAmount);
        $this->assertSame('active', $result->status);
        $this->assertSame('WIDGET-1', $result->sku);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Widget Pro',
            'price_amount' => 2999,
            'status' => 'active',
        ]);
    }

    public function test_execute_forProductInAnotherTenant_throwsProductNotFoundException(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Globex Inc', 'globex-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenantA->id, 'Widget', 'WIDGET-1', 1999, 'USD');

        $this->expectException(ProductNotFoundException::class);

        app(UpdateProductAction::class)->execute(
            id: $product->id,
            tenantId: $tenantB->id,
            name: 'Hijacked',
            description: null,
            priceAmount: 1,
            priceCurrency: 'USD',
            status: 'active',
        );
    }

    public function test_execute_forNonexistentProduct_throwsProductNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(ProductNotFoundException::class);

        app(UpdateProductAction::class)->execute(
            id: 999,
            tenantId: $tenant->id,
            name: 'Ghost',
            description: null,
            priceAmount: 1,
            priceCurrency: 'USD',
            status: 'active',
        );
    }
}
