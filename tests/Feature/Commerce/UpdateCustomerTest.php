<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\UpdateCustomerAction;
use App\Modules\Commerce\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\DuplicateEmailException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_updatesFieldsAndStatus(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $customer = app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Doe', 'jane@example.com');

        $result = app(UpdateCustomerAction::class)->execute(
            id: $customer->id,
            tenantId: $tenant->id,
            firstName: 'Janet',
            lastName: 'Doe',
            email: 'jane@example.com',
            status: 'blacklisted',
        );

        $this->assertSame('Janet', $result->firstName);
        $this->assertSame('blacklisted', $result->status);
    }

    public function test_execute_changingEmailToOneAlreadyUsedByAnotherCustomer_throwsDuplicateEmailException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(CreateCustomerAction::class)->execute($tenant->id, 'Jane', 'Doe', 'jane@example.com');
        $customerB = app(CreateCustomerAction::class)->execute($tenant->id, 'John', 'Smith', 'john@example.com');

        $this->expectException(DuplicateEmailException::class);

        app(UpdateCustomerAction::class)->execute(
            id: $customerB->id,
            tenantId: $tenant->id,
            firstName: 'John',
            lastName: 'Smith',
            email: 'jane@example.com',
        );
    }

    public function test_execute_forNonexistentCustomer_throwsCustomerNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(CustomerNotFoundException::class);

        app(UpdateCustomerAction::class)->execute(999, $tenant->id, 'Jane', 'Doe', 'jane@example.com');
    }
}
