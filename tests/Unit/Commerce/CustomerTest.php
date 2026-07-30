<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Customer;
use App\Modules\Commerce\Domain\ValueObjects\Address;
use App\Modules\Commerce\Domain\ValueObjects\CustomerStatus;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 */
class CustomerTest extends TestCase
{
    public function test_register_withDefaults_startsActiveWithNoAddress(): void
    {
        $customer = Customer::register(1, 'Jane', 'Doe', new Email('jane@example.com'));

        $this->assertNull($customer->id());
        $this->assertSame('Jane Doe', $customer->fullName());
        $this->assertSame(CustomerStatus::Active, $customer->status());
        $this->assertNull($customer->defaultAddress());
        $this->assertFalse($customer->isBlacklisted());
    }

    public function test_update_changesFieldsIncludingStatus(): void
    {
        $customer = Customer::register(1, 'Jane', 'Doe', new Email('jane@example.com'));
        $address = new Address('123 Main St', 'Springfield', 'IL', '62701', 'US');

        $customer->update(
            firstName: 'Janet',
            lastName: 'Doe',
            email: new Email('janet@example.com'),
            phone: '555-1234',
            defaultAddress: $address,
            notes: 'VIP customer',
            status: CustomerStatus::Blacklisted,
        );

        $this->assertSame('Janet Doe', $customer->fullName());
        $this->assertSame('janet@example.com', $customer->email()->value());
        $this->assertSame('555-1234', $customer->phone());
        $this->assertTrue($customer->defaultAddress()->equals($address));
        $this->assertSame('VIP customer', $customer->notes());
        $this->assertTrue($customer->isBlacklisted());
    }
}
