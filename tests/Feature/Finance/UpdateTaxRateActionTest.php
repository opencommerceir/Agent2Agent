<?php

namespace Tests\Feature\Finance;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Finance\Application\Actions\CreateTaxRateAction;
use App\Modules\Finance\Application\Actions\UpdateTaxRateAction;
use App\Modules\Finance\Domain\Exceptions\TaxRateNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UpdateTaxRateAction exercised directly — not wired to any MCP
 * capability this stage (see its own docblock).
 */
class UpdateTaxRateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_updatesRateAndActiveState(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $taxRate = app(CreateTaxRateAction::class)->execute($tenant->id, 'US-CA', 850);

        $result = app(UpdateTaxRateAction::class)->execute($taxRate->id, $tenant->id, 925, false);

        $this->assertSame(925, $result->ratePercentage);
        $this->assertFalse($result->isActive);
        $this->assertDatabaseHas('tax_rates', ['id' => $taxRate->id, 'rate_percentage' => 925, 'is_active' => false]);
    }

    public function test_execute_forNonexistentTaxRate_throwsTaxRateNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(TaxRateNotFoundException::class);

        app(UpdateTaxRateAction::class)->execute(999999, $tenant->id, 900, true);
    }

    public function test_execute_forTaxRateInAnotherTenant_throwsTaxRateNotFoundException(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Globex Inc', 'globex-'.uniqid());
        $taxRate = app(CreateTaxRateAction::class)->execute($tenantA->id, 'US-CA', 850);

        $this->expectException(TaxRateNotFoundException::class);

        app(UpdateTaxRateAction::class)->execute($taxRate->id, $tenantB->id, 900, true);
    }
}
