<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\CreateCategoryAction;
use App\Modules\Commerce\Application\Actions\ImportProductsAction;
use App\Modules\Commerce\Application\Services\CsvParser;
use App\Modules\Commerce\Application\Services\CsvValidator;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Services\CsvParserInterface;
use App\Modules\Commerce\Domain\Services\CsvValidatorInterface;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Action+Job-level tests for the Product CSV import (Phase 5, Stage 3,
 * §7.23). `ProcessBulkImportJob` runs synchronously under this suite's
 * `sync` queue driver, so by the time `ImportProductsAction::execute()`
 * returns below, the whole run has already completed.
 *
 * `CsvParserInterface`/`CsvValidatorInterface` are bound here in setUp()
 * rather than in CommerceServiceProvider — this stage's own orchestrator
 * owns that provider file for a parallel, non-overlapping slice of the
 * same feature and wires the real binding there; binding it locally here
 * keeps this test suite fully self-contained in the meantime without
 * touching a file outside this slice's scope.
 */
class ImportProductsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(CsvParserInterface::class, CsvParser::class);
        $this->app->bind(CsvValidatorInterface::class, CsvValidator::class);
    }

    public function test_importingMixOfGoodAndBadRows_reportsPartialWithExactCountsAndCreatesGoodProducts(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);
        $category = app(CreateCategoryAction::class)->execute($tenant->id, 'Electronics');

        $filePath = 'imports/products.csv';
        Storage::disk('local')->put("bulk_operations/{$filePath}", $this->csv([
            ['sku', 'name', 'price', 'currency', 'category', 'status', 'stock_quantity'],
            ['SKU-1', 'Widget One', '19.99', 'USD', $category->name, 'active', '50'],
            ['SKU-2', 'Widget Two', '29.99', 'USD', '', 'active', '30'],
            ['', 'Widget Three (no sku)', '9.99', 'USD', '', 'active', '10'],
            ['SKU-4', 'Widget Four (bad price)', 'not-a-number', 'USD', '', 'active', '10'],
        ]));

        $result = app(ImportProductsAction::class)->execute($tenant->id, $agentId, $filePath);

        $this->assertSame('partial', $result->status);
        $this->assertSame(4, $result->totalRows);
        $this->assertSame(4, $result->processedRows);
        $this->assertSame(2, $result->successRows);
        $this->assertSame(2, $result->failedRows);
        $this->assertNotNull($result->errorFilePath);

        $products = app(ProductRepositoryInterface::class);
        $inventories = app(InventoryRepositoryInterface::class);

        $product1 = $products->findBySku(new SKU('SKU-1'), $tenant->id);
        $this->assertNotNull($product1);
        $this->assertSame(1999, $product1->price()->amount());
        $this->assertSame($category->id, $product1->categoryId());
        $this->assertSame(50, $inventories->findByProduct($product1->id(), $tenant->id)->quantityOnHand());

        $product2 = $products->findBySku(new SKU('SKU-2'), $tenant->id);
        $this->assertNotNull($product2);
        $this->assertNull($product2->categoryId());
        $this->assertSame(30, $inventories->findByProduct($product2->id(), $tenant->id)->quantityOnHand());

        $this->assertNull($products->findBySku(new SKU('SKU-4'), $tenant->id));

        Storage::disk('public')->assertExists($result->errorFilePath);
        $errorCsv = Storage::disk('public')->get($result->errorFilePath);
        $this->assertStringContainsString('sku', $errorCsv);
        $this->assertStringContainsString('Invalid price', $errorCsv);
    }

    public function test_reimportingSameSkus_updatesExistingProductsInPlaceRatherThanDuplicating(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $firstPath = 'imports/first.csv';
        Storage::disk('local')->put("bulk_operations/{$firstPath}", $this->csv([
            ['sku', 'name', 'price', 'currency', 'category', 'status', 'stock_quantity'],
            ['SKU-1', 'Widget One', '19.99', 'USD', '', 'active', '50'],
        ]));

        $firstResult = app(ImportProductsAction::class)->execute($tenant->id, $agentId, $firstPath);
        $this->assertSame('completed', $firstResult->status);

        $secondPath = 'imports/second.csv';
        Storage::disk('local')->put("bulk_operations/{$secondPath}", $this->csv([
            ['sku', 'name', 'price', 'currency', 'category', 'status', 'stock_quantity'],
            ['SKU-1', 'Widget One Renamed', '25.00', 'USD', '', 'active', '5'],
        ]));

        $secondResult = app(ImportProductsAction::class)->execute($tenant->id, $agentId, $secondPath);
        $this->assertSame('completed', $secondResult->status);
        $this->assertSame(1, $secondResult->successRows);

        $products = app(ProductRepositoryInterface::class);
        $inventories = app(InventoryRepositoryInterface::class);

        $product = $products->findBySku(new SKU('SKU-1'), $tenant->id);
        $this->assertNotNull($product);
        $this->assertSame('Widget One Renamed', $product->name());
        $this->assertSame(2500, $product->price()->amount());
        $this->assertSame(5, $inventories->findByProduct($product->id(), $tenant->id)->quantityOnHand());
    }

    /**
     * The stage's own required larger-scale scenario: ~50 rows with ~5
     * deliberately invalid — kept at 50 (not 1000+) for test speed, since
     * the *proportional* validation-and-reporting behavior is what's being
     * proven, not raw throughput.
     */
    public function test_importing50RowsWithFiveInvalid_reportsExactCountsAndExactErrorReasons(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $rows = [['sku', 'name', 'price', 'currency', 'category', 'status', 'stock_quantity']];
        $invalidSkus = [];

        for ($i = 1; $i <= 50; $i++) {
            if ($i % 10 === 0) {
                // Every 10th row (5 total, i = 10/20/30/40/50) is missing its SKU.
                $rows[] = ['', "Bad Widget {$i}", '9.99', 'USD', '', 'active', '1'];
                $invalidSkus[] = $i;

                continue;
            }

            $rows[] = ["SKU-{$i}", "Widget {$i}", '9.99', 'USD', '', 'active', '1'];
        }

        $this->assertCount(5, $invalidSkus);

        $filePath = 'imports/fifty.csv';
        Storage::disk('local')->put("bulk_operations/{$filePath}", $this->csv($rows));

        $result = app(ImportProductsAction::class)->execute($tenant->id, $agentId, $filePath);

        $this->assertSame('partial', $result->status);
        $this->assertSame(50, $result->totalRows);
        $this->assertSame(50, $result->processedRows);
        $this->assertSame(45, $result->successRows);
        $this->assertSame(5, $result->failedRows);

        $errorCsv = Storage::disk('public')->get($result->errorFilePath);
        $errorLines = array_values(array_filter(explode("\n", trim($errorCsv))));

        // header + exactly 5 failed rows.
        $this->assertCount(6, $errorLines);

        foreach (array_slice($errorLines, 1) as $line) {
            $this->assertStringContainsString('Missing required column [sku]', $line);
        }
    }

    /**
     * @param list<list<string>> $rows including the header as the first row
     */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Bulk Import Assistant', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return $agent->id;
    }
}
