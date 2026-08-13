<?php

namespace Tests\Feature\Nexus\Catalog;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\AddServiceAction;
use App\Domains\Nexus\Catalog\Application\Actions\SearchCatalogAction;
use App\Domains\Nexus\Catalog\Application\Actions\UpdateProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\UpdateServiceAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CatalogActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBusinessId(): int
    {
        return app(RegisterBusinessAction::class)
            ->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology)
            ->id;
    }

    public function test_addProduct_withValidData_persistsProduct(): void
    {
        $businessId = $this->makeBusinessId();

        $result = app(AddProductAction::class)->execute($businessId, 'محصول آزمایشی', 'Test Product', 50000, 'IRT', 10);

        $this->assertNotNull($result->id);
        $this->assertSame(50000, $result->priceAmount);
        $this->assertDatabaseHas('nexus_products', ['id' => $result->id, 'business_id' => $businessId, 'name_en' => 'Test Product', 'stock_quantity' => 10]);
    }

    public function test_addService_withValidData_persistsService(): void
    {
        $businessId = $this->makeBusinessId();

        $result = app(AddServiceAction::class)->execute($businessId, 'خدمت آزمایشی', 'Test Service', 200000, 'IRT', 60);

        $this->assertNotNull($result->id);
        $this->assertSame(60, $result->durationMinutes);
        $this->assertDatabaseHas('nexus_services', ['id' => $result->id, 'business_id' => $businessId, 'name_en' => 'Test Service']);
    }

    public function test_updateProduct_withValidData_updatesProduct(): void
    {
        $businessId = $this->makeBusinessId();
        $product = app(AddProductAction::class)->execute($businessId, 'محصول آزمایشی', 'Test Product', 50000, 'IRT', 10);

        $result = app(UpdateProductAction::class)->execute($product->id, $businessId, 'محصول جدید', 'New Product', 75000, 'IRT', 5, ['color' => 'red']);

        $this->assertSame('New Product', $result->nameEn);
        $this->assertSame(75000, $result->priceAmount);
        $this->assertSame(5, $result->stockQuantity);
    }

    public function test_updateProduct_withNonExistentProduct_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(UpdateProductAction::class)->execute(9999, 1, 'x', 'y', 1, 'IRT', 0, null);
    }

    public function test_updateProduct_belongingToAnotherBusiness_throwsInvalidArgumentException(): void
    {
        $businessId = $this->makeBusinessId();
        $otherBusinessId = app(RegisterBusinessAction::class)->execute('شرکت دیگر', 'Other Company', BusinessType::Company, Industry::Retail)->id;
        $product = app(AddProductAction::class)->execute($businessId, 'محصول آزمایشی', 'Test Product', 50000, 'IRT', 10);

        $this->expectException(InvalidArgumentException::class);

        app(UpdateProductAction::class)->execute($product->id, $otherBusinessId, 'x', 'y', 1, 'IRT', 0, null);
    }

    public function test_updateService_withValidData_updatesService(): void
    {
        $businessId = $this->makeBusinessId();
        $service = app(AddServiceAction::class)->execute($businessId, 'خدمت آزمایشی', 'Test Service', 200000, 'IRT', 60);

        $result = app(UpdateServiceAction::class)->execute($service->id, $businessId, 'خدمت جدید', 'New Service', 300000, 'IRT', 90, null);

        $this->assertSame('New Service', $result->nameEn);
        $this->assertSame(90, $result->durationMinutes);
    }

    public function test_updateService_belongingToAnotherBusiness_throwsInvalidArgumentException(): void
    {
        $businessId = $this->makeBusinessId();
        $otherBusinessId = app(RegisterBusinessAction::class)->execute('شرکت دیگر', 'Other Company', BusinessType::Company, Industry::Retail)->id;
        $service = app(AddServiceAction::class)->execute($businessId, 'خدمت آزمایشی', 'Test Service', 200000, 'IRT', 60);

        $this->expectException(InvalidArgumentException::class);

        app(UpdateServiceAction::class)->execute($service->id, $otherBusinessId, 'x', 'y', 1, 'IRT', null, null);
    }

    public function test_searchCatalog_findsProductsAndServicesByBusinessAndQuery(): void
    {
        $businessId = $this->makeBusinessId();
        $otherBusinessId = app(RegisterBusinessAction::class)->execute('شرکت دیگر', 'Other Company', BusinessType::Company, Industry::Retail)->id;

        app(AddProductAction::class)->execute($businessId, 'لپ‌تاپ', 'Laptop Pro', 50000000, 'IRT');
        app(AddProductAction::class)->execute($businessId, 'ماوس', 'Wireless Mouse', 500000, 'IRT');
        app(AddServiceAction::class)->execute($businessId, 'نصب', 'Laptop Setup', 200000, 'IRT');
        app(AddProductAction::class)->execute($otherBusinessId, 'لپ‌تاپ', 'Laptop Air', 40000000, 'IRT');

        $result = app(SearchCatalogAction::class)->execute($businessId, 'Laptop');

        $this->assertCount(1, $result['products']);
        $this->assertSame('Laptop Pro', $result['products'][0]->nameEn);
        $this->assertCount(1, $result['services']);
        $this->assertSame('Laptop Setup', $result['services'][0]->nameEn);
    }

    public function test_searchCatalog_withEmptyQuery_returnsWholeBusinessCatalog(): void
    {
        $businessId = $this->makeBusinessId();
        app(AddProductAction::class)->execute($businessId, 'محصول', 'Product A', 1000, 'IRT');
        app(AddProductAction::class)->execute($businessId, 'محصول', 'Product B', 2000, 'IRT');

        $result = app(SearchCatalogAction::class)->execute($businessId, '');

        $this->assertCount(2, $result['products']);
    }
}
