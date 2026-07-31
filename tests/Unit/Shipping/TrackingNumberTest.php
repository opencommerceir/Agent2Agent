<?php

namespace Tests\Unit\Shipping;

use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TrackingNumberTest extends TestCase
{
    public function test_generate_producesValidFormat(): void
    {
        $trackingNumber = TrackingNumber::generate();

        $this->assertMatchesRegularExpression('/^TRK-[A-Z0-9]{8}$/', $trackingNumber->value());
    }

    public function test_generate_producesDifferentValuesEachTime(): void
    {
        $first = TrackingNumber::generate();
        $second = TrackingNumber::generate();

        // Astronomically unlikely to collide (36^8 possibilities) — a
        // real collision-check/retry lives in CreateShipmentAction, not here.
        $this->assertNotSame($first->value(), $second->value());
    }

    public function test_construct_withValidFormat_succeeds(): void
    {
        $trackingNumber = new TrackingNumber('TRK-ABCD1234');

        $this->assertSame('TRK-ABCD1234', $trackingNumber->value());
    }

    public function test_construct_withInvalidFormat_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrackingNumber('not-a-tracking-number');
    }

    public function test_equals_withSameValue_returnsTrue(): void
    {
        $this->assertTrue((new TrackingNumber('TRK-ABCD1234'))->equals(new TrackingNumber('TRK-ABCD1234')));
    }
}
