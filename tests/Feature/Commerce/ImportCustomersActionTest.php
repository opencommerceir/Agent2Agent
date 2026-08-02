<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\ImportCustomersAction;
use App\Modules\Commerce\Application\Services\CsvParser;
use App\Modules\Commerce\Application\Services\CsvValidator;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Services\CsvParserInterface;
use App\Modules\Commerce\Domain\Services\CsvValidatorInterface;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Action+Job-level tests for the Customer CSV import (Phase 5, Stage 3,
 * §7.23) — same shape as ImportProductsActionTest, smaller. See that
 * file's own class docblock for why CsvParserInterface/CsvValidatorInterface
 * are bound locally in setUp() rather than in CommerceServiceProvider.
 */
class ImportCustomersActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(CsvParserInterface::class, CsvParser::class);
        $this->app->bind(CsvValidatorInterface::class, CsvValidator::class);
    }

    public function test_importingMixOfGoodAndBadRows_reportsPartialAndCreatesGoodCustomers(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $filePath = 'imports/customers.csv';
        Storage::disk('local')->put("bulk_operations/{$filePath}", $this->csv([
            ['email', 'first_name', 'last_name', 'phone'],
            ['jane@example.com', 'Jane', 'Doe', '555-0100'],
            ['john@example.com', 'John', 'Smith', ''],
            ['not-an-email', 'Bad', 'Email', ''],
            ['', 'Missing', 'Email', ''],
        ]));

        $result = app(ImportCustomersAction::class)->execute($tenant->id, $agentId, $filePath);

        $this->assertSame('partial', $result->status);
        $this->assertSame(4, $result->totalRows);
        $this->assertSame(2, $result->successRows);
        $this->assertSame(2, $result->failedRows);

        $customers = app(CustomerRepositoryInterface::class);

        $jane = $customers->findByEmail(new Email('jane@example.com'), $tenant->id);
        $this->assertNotNull($jane);
        $this->assertSame('Jane', $jane->firstName());
        $this->assertSame('555-0100', $jane->phone());

        $john = $customers->findByEmail(new Email('john@example.com'), $tenant->id);
        $this->assertNotNull($john);
        $this->assertNull($john->phone());

        Storage::disk('public')->assertExists($result->errorFilePath);
    }

    public function test_reimportingSameEmail_updatesExistingCustomerInPlace(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $firstPath = 'imports/first.csv';
        Storage::disk('local')->put("bulk_operations/{$firstPath}", $this->csv([
            ['email', 'first_name', 'last_name', 'phone'],
            ['jane@example.com', 'Jane', 'Doe', '555-0100'],
        ]));

        app(ImportCustomersAction::class)->execute($tenant->id, $agentId, $firstPath);

        $secondPath = 'imports/second.csv';
        Storage::disk('local')->put("bulk_operations/{$secondPath}", $this->csv([
            ['email', 'first_name', 'last_name', 'phone'],
            ['jane@example.com', 'Jane', 'Doe-Updated', '555-9999'],
        ]));

        $result = app(ImportCustomersAction::class)->execute($tenant->id, $agentId, $secondPath);

        $this->assertSame('completed', $result->status);
        $this->assertSame(1, $result->successRows);

        $customers = app(CustomerRepositoryInterface::class);
        $jane = $customers->findByEmail(new Email('jane@example.com'), $tenant->id);
        $this->assertSame('Doe-Updated', $jane->lastName());
        $this->assertSame('555-9999', $jane->phone());
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
