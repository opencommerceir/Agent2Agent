<?php

namespace App\Modules\Notifications\Domain\Entities;

use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use DateTimeImmutable;

/**
 * One per (tenant, ChannelType) — `ConfigureChannelAction` upserts rather
 * than always inserting (`configure()` is the update path for a channel
 * that's already been set up once). `config` is stored/returned
 * faithfully but this stage's Senders don't actually consume it (e.g.
 * `EmailSender` uses Laravel's own global mailer config, not a per-tenant
 * override) — `isActive` is the only thing `NotificationDispatcher`
 * actually gates on. A real per-tenant SMTP override is real future work,
 * not silently broken behavior.
 */
final class NotificationChannel
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly ChannelType $channelType,
        private array $config,
        private bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        ChannelType $channelType,
        array $config = [],
        bool $isActive = true,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            channelType: $channelType,
            config: $config,
            isActive: $isActive,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function configure(array $config, bool $isActive): void
    {
        $this->config = $config;
        $this->isActive = $isActive;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function channelType(): ChannelType
    {
        return $this->channelType;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
