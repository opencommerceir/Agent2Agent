<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Address;
use App\Modules\Commerce\Domain\ValueObjects\CustomerStatus;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use DateTimeImmutable;

/**
 * A Customer is independent of Order — nothing here references an Order,
 * and Order only optionally references a Customer (customer_id is
 * nullable on the `orders` table). This is deliberate: Customer and
 * Order are two aggregates in the same Commerce module that interact
 * only through explicit ids and Repository interfaces (Dependency
 * Inversion), never through direct object references — see
 * GetCustomerOrdersAction for the one place they meet.
 */
final class Customer
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private string $firstName,
        private string $lastName,
        private Email $email,
        private ?string $phone,
        private CustomerStatus $status,
        private ?Address $defaultAddress,
        private ?string $notes,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(
        int $tenantId,
        string $firstName,
        string $lastName,
        Email $email,
        ?string $phone = null,
        ?Address $defaultAddress = null,
        ?string $notes = null,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            phone: $phone,
            status: CustomerStatus::Active,
            defaultAddress: $defaultAddress,
            notes: $notes,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(
        string $firstName,
        string $lastName,
        Email $email,
        ?string $phone,
        ?Address $defaultAddress,
        ?string $notes,
        CustomerStatus $status,
    ): void {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phone = $phone;
        $this->defaultAddress = $defaultAddress;
        $this->notes = $notes;
        $this->status = $status;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function status(): CustomerStatus
    {
        return $this->status;
    }

    public function defaultAddress(): ?Address
    {
        return $this->defaultAddress;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isBlacklisted(): bool
    {
        return $this->status === CustomerStatus::Blacklisted;
    }
}
