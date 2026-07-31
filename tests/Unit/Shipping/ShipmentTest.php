<?php

namespace Tests\Unit\Shipping;

use App\Modules\Shipping\Domain\Entities\Shipment;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;
use App\Modules\Shipping\Domain\ValueObjects\Weight;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ShipmentTest extends TestCase
{
    private function makeShipment(): Shipment
    {
        return Shipment::create(
            1,
            1,
            1,
            TrackingNumber::generate(),
            new Weight(1000),
            Money::fromAmount(500, 'USD'),
        );
    }

    public function test_create_startsAtPendingWithNoShippedOrDeliveredDates(): void
    {
        $shipment = $this->makeShipment();

        $this->assertSame(TrackingStatus::Pending, $shipment->status());
        $this->assertNull($shipment->shippedAt());
        $this->assertNull($shipment->deliveredAt());
    }

    public function test_changeStatus_pendingToInTransit_stampsShippedAt(): void
    {
        $shipment = $this->makeShipment();

        $shipment->changeStatus(TrackingStatus::InTransit);

        $this->assertSame(TrackingStatus::InTransit, $shipment->status());
        $this->assertNotNull($shipment->shippedAt());
        $this->assertNull($shipment->deliveredAt());
    }

    public function test_changeStatus_inTransitToDelivered_stampsDeliveredAt(): void
    {
        $shipment = $this->makeShipment();
        $shipment->changeStatus(TrackingStatus::InTransit);

        $shipment->changeStatus(TrackingStatus::Delivered);

        $this->assertSame(TrackingStatus::Delivered, $shipment->status());
        $this->assertNotNull($shipment->deliveredAt());
    }

    public function test_changeStatus_pendingDirectlyToDelivered_throwsInvalidArgumentException(): void
    {
        $shipment = $this->makeShipment();

        $this->expectException(InvalidArgumentException::class);

        $shipment->changeStatus(TrackingStatus::Delivered);
    }

    public function test_changeStatus_fromDelivered_isTerminal(): void
    {
        $shipment = $this->makeShipment();
        $shipment->changeStatus(TrackingStatus::InTransit);
        $shipment->changeStatus(TrackingStatus::Delivered);

        $this->expectException(InvalidArgumentException::class);

        $shipment->changeStatus(TrackingStatus::InTransit);
    }

    public function test_changeStatus_exceptionIsRecoverableBackToInTransit(): void
    {
        $shipment = $this->makeShipment();
        $shipment->changeStatus(TrackingStatus::InTransit);
        $shipment->changeStatus(TrackingStatus::Exception);

        $shipment->changeStatus(TrackingStatus::InTransit);

        $this->assertSame(TrackingStatus::InTransit, $shipment->status());
    }

    public function test_changeStatus_fromReturned_isTerminal(): void
    {
        $shipment = $this->makeShipment();
        $shipment->changeStatus(TrackingStatus::Returned);

        $this->expectException(InvalidArgumentException::class);

        $shipment->changeStatus(TrackingStatus::InTransit);
    }
}
