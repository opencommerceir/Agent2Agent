<?php

namespace App\Modules\Shipping\Domain\Entities;

use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use App\Modules\Shipping\Domain\ValueObjects\TrackingStatus;
use App\Modules\Shipping\Domain\ValueObjects\Weight;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One physical shipment fulfilling one Commerce Order. `orderId` is a
 * plain int, not a direct object reference — Shipping and Commerce meet
 * only through explicit ids and Commerce's own Repository interface
 * (Dependency Inversion), the same "two aggregates in different modules
 * meet only through ids" shape CRM/Finance/Loyalty already established.
 *
 * State machine (rule §d.3: "pending → in_transit → delivered (یا
 * returned/exception)"): `Delivered`/`Returned` are the only true
 * terminal states; `Exception` is deliberately recoverable (a carrier
 * problem can be resolved and the shipment resumes transit) — see
 * TrackingStatus's own docblock. `changeStatus()` also stamps
 * `shipped_at`/`delivered_at` the first time the Shipment ever reaches
 * `InTransit`/`Delivered`, mirroring Order's own `confirm()`/`cancel()`
 * pattern of a status transition carrying its own side effect.
 */
final class Shipment
{
    /**
     * @var array<string, list<TrackingStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => [TrackingStatus::InTransit, TrackingStatus::Exception, TrackingStatus::Returned],
        'in_transit' => [TrackingStatus::Delivered, TrackingStatus::Returned, TrackingStatus::Exception],
        'exception' => [TrackingStatus::InTransit, TrackingStatus::Returned],
        'delivered' => [],
        'returned' => [],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $orderId,
        private readonly int $shippingMethodId,
        private readonly TrackingNumber $trackingNumber,
        private TrackingStatus $status,
        private readonly Weight $weight,
        private readonly Money $shippingCost,
        private ?DateTimeImmutable $shippedAt,
        private ?DateTimeImmutable $deliveredAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        int $orderId,
        int $shippingMethodId,
        TrackingNumber $trackingNumber,
        Weight $weight,
        Money $shippingCost,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            orderId: $orderId,
            shippingMethodId: $shippingMethodId,
            trackingNumber: $trackingNumber,
            status: TrackingStatus::Pending,
            weight: $weight,
            shippingCost: $shippingCost,
            shippedAt: null,
            deliveredAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function changeStatus(TrackingStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "Shipment cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;

        if ($newStatus === TrackingStatus::InTransit && $this->shippedAt === null) {
            $this->shippedAt = new DateTimeImmutable();
        }

        if ($newStatus === TrackingStatus::Delivered) {
            $this->deliveredAt = new DateTimeImmutable();
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function shippingMethodId(): int
    {
        return $this->shippingMethodId;
    }

    public function trackingNumber(): TrackingNumber
    {
        return $this->trackingNumber;
    }

    public function status(): TrackingStatus
    {
        return $this->status;
    }

    public function weight(): Weight
    {
        return $this->weight;
    }

    public function shippingCost(): Money
    {
        return $this->shippingCost;
    }

    public function shippedAt(): ?DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function deliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
