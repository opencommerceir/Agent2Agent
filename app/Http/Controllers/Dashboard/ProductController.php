<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\Actions\GetProductAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reuses Commerce's own ListProductsAction/GetProductAction directly — the
 * same Actions `commerce.product.search`'s MCP handler calls — rather than
 * querying ProductRepositoryInterface itself (Dashboard Controllers Rule:
 * no business logic in Controllers, use existing Actions).
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly ListProductsAction $listProducts,
        private readonly GetProductAction $getProduct,
    ) {
    }

    public function index(Request $request): View
    {
        $tenants = $this->tenants->all();
        $tenantId = $request->integer('tenant_id') ?: (($tenants[0] ?? null)?->id());

        $products = $tenantId !== null
            ? $this->listProducts->execute(['query' => $request->string('query')->toString() ?: null], $tenantId)['products']
            : [];

        return view('dashboard.products.index', [
            'products' => $products,
            'tenants' => $tenants,
            'selectedTenantId' => $tenantId,
        ]);
    }

    public function show(Request $request, int $productId): View
    {
        $tenantId = (int) $request->integer('tenant_id');

        $product = $this->getProduct->execute($productId, $tenantId);

        return view('dashboard.products.show', ['product' => $product]);
    }
}
