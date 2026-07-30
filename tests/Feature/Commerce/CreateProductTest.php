<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCategoryAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Exceptions\DuplicateSKUException;
use App\Modules\Commerce\Domain\Exceptions\InvalidSKUException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_persistsProductAsDraft(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $result = app(CreateProductAction::class)->execute(
            tenantId: $tenant->id,
            name: 'Widget',
            sku: 'widget-1',
            priceAmount: 1999,
            priceCurrency: 'usd',
        );

        $this->assertNotNull($result->id);
        $this->assertSame('WIDGET-1', $result->sku);
        $this->assertSame(1999, $result->priceAmount);
        $this->assertSame('USD', $result->priceCurrency);
        $this->assertSame('draft', $result->status);
        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'WIDGET-1',
            'price_amount' => 1999,
            'price_currency' => 'USD',
        ]);
    }

    public function test_execute_withDuplicateSkuInSameTenant_throwsDuplicateSKUExceptionAndDoesNotDuplicateRow(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD');

        $this->expectException(DuplicateSKUException::class);

        try {
            app(CreateProductAction::class)->execute($tenant->id, 'Widget Two', 'widget-1', 2999, 'USD');
        } finally {
            $this->assertDatabaseCount('products', 1);
        }
    }

    public function test_execute_withSameSkuInDifferentTenants_doesNotConflict(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Globex Inc', 'globex-'.uniqid());

        app(CreateProductAction::class)->execute($tenantA->id, 'Widget', 'WIDGET-1', 1999, 'USD');
        $result = app(CreateProductAction::class)->execute($tenantB->id, 'Widget', 'WIDGET-1', 1999, 'USD');

        $this->assertNotNull($result->id);
        $this->assertDatabaseCount('products', 2);
    }

    public function test_execute_withInvalidSkuFormat_throwsInvalidSKUExceptionAndPersistsNothing(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(InvalidSKUException::class);

        try {
            app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'a b', 1999, 'USD');
        } finally {
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_execute_withCategoryId_persistsCategoryAssociation(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $category = app(CreateCategoryAction::class)->execute($tenant->id, 'Electronics');

        $result = app(CreateProductAction::class)->execute(
            tenantId: $tenant->id,
            name: 'Widget',
            sku: 'WIDGET-1',
            priceAmount: 1999,
            priceCurrency: 'USD',
            categoryId: $category->id,
        );

        $this->assertSame($category->id, $result->categoryId);
    }
}
