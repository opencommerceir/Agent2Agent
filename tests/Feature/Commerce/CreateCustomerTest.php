<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Domain\Exceptions\DuplicateEmailException;
use App\Modules\Commerce\Domain\Exceptions\InvalidEmailException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_persistsCustomer(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $result = app(CreateCustomerAction::class)->execute(
            tenantId: $tenant->id,
            firstName: 'Jane',
            lastName: 'Doe',
            email: 'Jane@Example.com',
            phone: '555-1234',
            address: ['street' => '123 Main St', 'city' => 'Springfield', 'country' => 'US'],
        );

        $this->assertNotNull($result->id);
        $this->assertSame('jane@example.com', $result->email);
        $this->assertSame('active', $result->status);
        $this->assertSame('123 Main St', $result->defaultAddress['street']);
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'email' => 'jane@example.com',
        ]);
    }

    public function test_execute_withDuplicateEmailInSameTenant_throwsDuplicateEmailExceptionAndDoesNotDuplicateRow(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Doe', 'jane@example.com');

        $this->expectException(DuplicateEmailException::class);

        try {
            app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Other', 'JANE@EXAMPLE.COM');
        } finally {
            $this->assertDatabaseCount('customers', 1);
        }
    }

    public function test_execute_withSameEmailInDifferentTenants_doesNotConflict(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Globex Inc', 'globex-'.uniqid());

        app(CreateCustomerAction::class)->execute($tenantA->id, 'Jane', 'Doe', 'jane@example.com');
        $result = app(CreateCustomerAction::class)->execute($tenantB->id, 'Jane', 'Doe', 'jane@example.com');

        $this->assertNotNull($result->id);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_execute_withInvalidEmailFormat_throwsInvalidEmailExceptionAndPersistsNothing(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(InvalidEmailException::class);

        try {
            app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Doe', 'not-an-email');
        } finally {
            $this->assertDatabaseCount('customers', 0);
        }
    }
}
