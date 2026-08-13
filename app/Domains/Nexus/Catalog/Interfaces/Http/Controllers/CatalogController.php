<?php

namespace App\Domains\Nexus\Catalog\Interfaces\Http\Controllers;

use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\AddServiceAction;
use App\Domains\Nexus\Catalog\Application\Actions\SearchCatalogAction;
use App\Domains\Nexus\Catalog\Application\Actions\UpdateProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\UpdateServiceAction;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The self-service portal counterpart to AddProductAction/AddServiceAction/
 * UpdateProductAction/UpdateServiceAction/SearchCatalogAction (Phase 1/M4)
 * — those Actions existed since the very first phase but, until now, were
 * only ever reachable through tinker/tests, not a real form. Currency is
 * hardcoded to 'IRT' here, matching the literal used everywhere else Money
 * is constructed in this domain (e.g. CatalogActionsTest, PurchaseCreditsAction)
 * — Nexus has no multi-currency catalog requirement to justify a picker.
 */
class CatalogController extends Controller
{
    public function __construct(
        private readonly SearchCatalogAction $searchCatalog,
        private readonly AddProductAction $addProduct,
        private readonly AddServiceAction $addService,
        private readonly UpdateProductAction $updateProduct,
        private readonly UpdateServiceAction $updateService,
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function index(Request $request): View
    {
        $businessId = $this->actingBusinessId();
        $query = trim((string) $request->query('q', ''));

        $result = $this->searchCatalog->execute($businessId, $query);

        return view('nexus::catalog.index', [
            'products' => $result['products'],
            'services' => $result['services'],
            'query' => $query,
        ]);
    }

    public function createProduct(): View
    {
        return view('nexus::catalog.products.create');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $businessId = $this->actingBusinessId();

        $validated = $request->validate([
            'name_fa' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $this->addProduct->execute(
            businessId: $businessId,
            nameFa: $validated['name_fa'],
            nameEn: $validated['name_en'],
            priceAmount: (int) $validated['price_amount'],
            priceCurrency: 'IRT',
            stockQuantity: (int) $validated['stock_quantity'],
        );

        return redirect()->route('nexus.catalog.index')->with('status', t('messages.nexus.catalog.products.created'));
    }

    public function editProduct(int $product): View
    {
        $businessId = $this->actingBusinessId();
        $entity = $this->products->findById($product);

        if (! $entity || $entity->businessId() !== $businessId) {
            abort(403);
        }

        return view('nexus::catalog.products.edit', ['product' => $entity]);
    }

    public function updateProduct(Request $request, int $product): RedirectResponse
    {
        $businessId = $this->actingBusinessId();

        $validated = $request->validate([
            'name_fa' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $existing = $this->products->findById($product);
        $attributes = $existing?->businessId() === $businessId ? $existing->attributes() : null;

        try {
            $this->updateProduct->execute(
                productId: $product,
                businessId: $businessId,
                nameFa: $validated['name_fa'],
                nameEn: $validated['name_en'],
                priceAmount: (int) $validated['price_amount'],
                priceCurrency: 'IRT',
                stockQuantity: (int) $validated['stock_quantity'],
                attributes: $attributes,
            );
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return redirect()->route('nexus.catalog.index')->with('status', t('messages.nexus.catalog.products.updated'));
    }

    public function createService(): View
    {
        return view('nexus::catalog.services.create');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $businessId = $this->actingBusinessId();

        $validated = $request->validate([
            'name_fa' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->addService->execute(
            businessId: $businessId,
            nameFa: $validated['name_fa'],
            nameEn: $validated['name_en'],
            priceAmount: (int) $validated['price_amount'],
            priceCurrency: 'IRT',
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
        );

        return redirect()->route('nexus.catalog.index')->with('status', t('messages.nexus.catalog.services.created'));
    }

    public function editService(int $service): View
    {
        $businessId = $this->actingBusinessId();
        $entity = $this->services->findById($service);

        if (! $entity || $entity->businessId() !== $businessId) {
            abort(403);
        }

        return view('nexus::catalog.services.edit', ['service' => $entity]);
    }

    public function updateService(Request $request, int $service): RedirectResponse
    {
        $businessId = $this->actingBusinessId();

        $validated = $request->validate([
            'name_fa' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $existing = $this->services->findById($service);
        $attributes = $existing?->businessId() === $businessId ? $existing->attributes() : null;

        try {
            $this->updateService->execute(
                serviceId: $service,
                businessId: $businessId,
                nameFa: $validated['name_fa'],
                nameEn: $validated['name_en'],
                priceAmount: (int) $validated['price_amount'],
                priceCurrency: 'IRT',
                durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
                attributes: $attributes,
            );
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return redirect()->route('nexus.catalog.index')->with('status', t('messages.nexus.catalog.services.updated'));
    }

    private function actingBusinessId(): int
    {
        return Auth::guard('business')->user()->business_id;
    }
}
