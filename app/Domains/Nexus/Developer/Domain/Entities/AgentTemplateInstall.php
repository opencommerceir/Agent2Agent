<?php

namespace App\Domains\Nexus\Developer\Domain\Entities;

use DateTimeImmutable;

/**
 * One immutable row per template install — the revenue-sharing ledger,
 * same append-only shape CreditTransaction/WebhookDeliveryLog already
 * established.
 */
final class AgentTemplateInstall
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $templateId,
        private readonly int $installingBusinessId,
        private readonly int $publisherBusinessId,
        private readonly int $priceCredits,
        private readonly int $platformFeeCredits,
        private readonly int $publisherEarningsCredits,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $templateId,
        int $installingBusinessId,
        int $publisherBusinessId,
        int $priceCredits,
        int $platformFeeCredits,
        int $publisherEarningsCredits,
    ): self {
        return new self(
            id: null,
            templateId: $templateId,
            installingBusinessId: $installingBusinessId,
            publisherBusinessId: $publisherBusinessId,
            priceCredits: $priceCredits,
            platformFeeCredits: $platformFeeCredits,
            publisherEarningsCredits: $publisherEarningsCredits,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function templateId(): int
    {
        return $this->templateId;
    }

    public function installingBusinessId(): int
    {
        return $this->installingBusinessId;
    }

    public function publisherBusinessId(): int
    {
        return $this->publisherBusinessId;
    }

    public function priceCredits(): int
    {
        return $this->priceCredits;
    }

    public function platformFeeCredits(): int
    {
        return $this->platformFeeCredits;
    }

    public function publisherEarningsCredits(): int
    {
        return $this->publisherEarningsCredits;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
