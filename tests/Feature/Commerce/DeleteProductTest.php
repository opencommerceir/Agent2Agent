<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\DeleteProductAction;
use App\Modules\Commerce\Application\Actions\GetProductAction;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Infrastructure\Models\Product as ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_softDeletesProduct_hidingItFromNormalQueriesButKeepingTheRow(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD');

        app(DeleteProductAction::class)->execute($product->id, $tenant->id);

        $this->assertSoftDeleted((new ProductModel())->getTable(), ['id' => $product->id]);

        $this->expectException(ProductNotFoundException::class);
        app(GetProductAction::class)->execute($product->id, $tenant->id);
    }

    public function test_execute_forNonexistentProduct_throwsProductNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(ProductNotFoundException::class);

        app(DeleteProductAction::class)->execute(999, $tenant->id);
    }
}
