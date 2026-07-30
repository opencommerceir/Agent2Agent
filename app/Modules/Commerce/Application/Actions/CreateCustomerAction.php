<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\CustomerData;
use App\Modules\Commerce\Domain\Entities\Customer;
use App\Modules\Commerce\Domain\Events\CustomerWasCreated;
use App\Modules\Commerce\Domain\Exceptions\DuplicateEmailException;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Address;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use Illuminate\Support\Facades\Event;

/**
 * One Action = one business operation: register a Customer and dispatch
 * the corresponding domain event. Email uniqueness is enforced per
 * tenant (Multi-Tenancy default), not globally — two different tenants
 * may both have a customer at the same email address.
 */
final class CreateCustomerAction
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /**
     * @param array<string, mixed>|null $address
     */
    public function execute(
        int $tenantId,
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone = null,
        ?array $address = null,
        ?string $notes = null,
    ): CustomerData {
        $emailValue = new Email($email); // throws InvalidEmailException on bad format

        if ($this->customers->emailExists($emailValue, $tenantId)) {
            throw new DuplicateEmailException("Email [{$emailValue}] already exists for this tenant.");
        }

        $customer = Customer::register(
            tenantId: $tenantId,
            firstName: $firstName,
            lastName: $lastName,
            email: $emailValue,
            phone: $phone,
            defaultAddress: $address !== null ? Address::fromArray($address) : null,
            notes: $notes,
        );

        $customer = $this->customers->save($customer);

        Event::dispatch(new CustomerWasCreated($customer));

        return CustomerData::fromEntity($customer);
    }
}
