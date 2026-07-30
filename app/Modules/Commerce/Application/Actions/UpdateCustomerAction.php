<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\CustomerData;
use App\Modules\Commerce\Domain\Events\CustomerWasUpdated;
use App\Modules\Commerce\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\DuplicateEmailException;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Address;
use App\Modules\Commerce\Domain\ValueObjects\CustomerStatus;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use Illuminate\Support\Facades\Event;

final class UpdateCustomerAction
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /**
     * @param array<string, mixed>|null $address
     */
    public function execute(
        int $id,
        int $tenantId,
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone = null,
        ?array $address = null,
        ?string $notes = null,
        string $status = 'active',
    ): CustomerData {
        $customer = $this->customers->findById($id, $tenantId);

        if (! $customer) {
            throw new CustomerNotFoundException("Customer [{$id}] does not exist.");
        }

        $emailValue = new Email($email); // throws InvalidEmailException on bad format

        if (! $customer->email()->equals($emailValue)) {
            $existing = $this->customers->findByEmail($emailValue, $tenantId);

            if ($existing && $existing->id() !== $customer->id()) {
                throw new DuplicateEmailException("Email [{$emailValue}] already exists for this tenant.");
            }
        }

        $customer->update(
            firstName: $firstName,
            lastName: $lastName,
            email: $emailValue,
            phone: $phone,
            defaultAddress: $address !== null ? Address::fromArray($address) : null,
            notes: $notes,
            status: CustomerStatus::from($status),
        );

        $customer = $this->customers->save($customer);

        Event::dispatch(new CustomerWasUpdated($customer));

        return CustomerData::fromEntity($customer);
    }
}
