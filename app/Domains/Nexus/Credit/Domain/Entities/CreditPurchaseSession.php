<?php

namespace App\Domains\Nexus\Credit\Domain\Entities;

use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPurchaseSessionStatus;
use App\Domains\Nexus\Credit\Domain\ValueObjects\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Bridges "we asked a redirect-based gateway to start a charge" and "the
 * gateway confirmed it" — the same role Commerce's own PaymentSession
 * plays for checkout, trimmed to what a credit purchase actually needs
 * (no cart/tax/discount/coupon). `total`/`package` are frozen at
 * initiate() time, same "compute once, apply durably later" principle
 * PaymentSession's own docblock establishes.
 *
 * `id` starts `null`, assigned exactly once by the repository
 * (`assignId()`) — a real id must exist before `initiate()` is called,
 * since it's what the gateway callback later looks this session up by.
 * `providerReference` is likewise assigned exactly once, by
 * `markInitiated()`.
 */
final class CreditPurchaseSession
{
    /**
     * @var array<string, list<CreditPurchaseSessionStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => [CreditPurchaseSessionStatus::Completed, CreditPurchaseSessionStatus::Failed, CreditPurchaseSessionStatus::Cancelled],
    ];

    private function __construct(
        private ?int $id,
        private readonly int $businessId,
        private readonly string $gateway,
        private ?string $providerReference,
        private readonly CreditPackage $package,
        private readonly Money $total,
        private CreditPurchaseSessionStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt,
    ) {
    }

    public static function create(int $businessId, string $gateway, CreditPackage $package, Money $total): self
    {
        return new self(
            id: null,
            businessId: $businessId,
            gateway: $gateway,
            providerReference: null,
            package: $package,
            total: $total,
            status: CreditPurchaseSessionStatus::Pending,
            createdAt: new DateTimeImmutable(),
            completedAt: null,
        );
    }

    public static function reconstruct(
        int $id,
        int $businessId,
        string $gateway,
        ?string $providerReference,
        CreditPackage $package,
        Money $total,
        CreditPurchaseSessionStatus $status,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $completedAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            gateway: $gateway,
            providerReference: $providerReference,
            package: $package,
            total: $total,
            status: $status,
            createdAt: $createdAt,
            completedAt: $completedAt,
        );
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException("CreditPurchaseSession already has id [{$this->id}]; assignId() is one-time only.");
        }

        $this->id = $id;
    }

    public function markInitiated(string $providerReference): void
    {
        if ($this->providerReference !== null) {
            throw new LogicException(
                "CreditPurchaseSession [{$this->id}] already has a providerReference; markInitiated() is one-time only."
            );
        }

        $this->providerReference = $providerReference;
    }

    public function complete(): void
    {
        $this->transitionTo(CreditPurchaseSessionStatus::Completed);
        $this->completedAt = new DateTimeImmutable();
    }

    public function fail(): void
    {
        $this->transitionTo(CreditPurchaseSessionStatus::Failed);
        $this->completedAt = new DateTimeImmutable();
    }

    private function transitionTo(CreditPurchaseSessionStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot transition CreditPurchaseSession from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function gateway(): string
    {
        return $this->gateway;
    }

    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    public function package(): CreditPackage
    {
        return $this->package;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function status(): CreditPurchaseSessionStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isPending(): bool
    {
        return $this->status === CreditPurchaseSessionStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === CreditPurchaseSessionStatus::Completed;
    }
}
