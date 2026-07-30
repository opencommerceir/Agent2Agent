<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\Address;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function test_construct_withValidData_setsAllFields(): void
    {
        $address = new Address('123 Main St', 'Springfield', 'IL', '62701', 'US');

        $this->assertSame('123 Main St', $address->street);
        $this->assertSame('Springfield', $address->city);
        $this->assertSame('IL', $address->state);
        $this->assertSame('62701', $address->postalCode);
        $this->assertSame('US', $address->country);
    }

    public function test_construct_withoutStateOrPostalCode_isStillValid(): void
    {
        $address = new Address('123 Main St', 'Springfield', null, null, 'US');

        $this->assertNull($address->state);
        $this->assertNull($address->postalCode);
    }

    public function test_construct_withEmptyStreet_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Address('', 'Springfield', 'IL', '62701', 'US');
    }

    public function test_fromArray_thenToArray_roundTrips(): void
    {
        $original = new Address('123 Main St', 'Springfield', 'IL', '62701', 'US');

        $rebuilt = Address::fromArray($original->toArray());

        $this->assertTrue($original->equals($rebuilt));
    }

    public function test_fromArray_acceptsSnakeCasePostalCode(): void
    {
        $address = Address::fromArray([
            'street' => '123 Main St',
            'city' => 'Springfield',
            'country' => 'US',
            'postal_code' => '62701',
        ]);

        $this->assertSame('62701', $address->postalCode);
    }
}
