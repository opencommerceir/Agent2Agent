<?php

namespace App\Modules\Notifications\Domain\Entities;

use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use DateTimeImmutable;

/**
 * A reusable, tenant-owned subject/body pair with `{{variable}}`
 * placeholders (`TemplateRenderer` is the one place these get rendered).
 * `variables` is a plain documentation list of the names a caller may
 * substitute — nothing enforces every listed name actually appears in
 * either template string, or vice versa.
 */
final class NotificationTemplate
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly NotificationType $type,
        private readonly ChannelType $channelType,
        private readonly string $subjectTemplate,
        private readonly string $bodyTemplate,
        private readonly array $variables,
        private readonly bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        NotificationType $type,
        ChannelType $channelType,
        string $subjectTemplate,
        string $bodyTemplate,
        array $variables = [],
        bool $isActive = true,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            type: $type,
            channelType: $channelType,
            subjectTemplate: $subjectTemplate,
            bodyTemplate: $bodyTemplate,
            variables: $variables,
            isActive: $isActive,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function type(): NotificationType
    {
        return $this->type;
    }

    public function channelType(): ChannelType
    {
        return $this->channelType;
    }

    public function subjectTemplate(): string
    {
        return $this->subjectTemplate;
    }

    public function bodyTemplate(): string
    {
        return $this->bodyTemplate;
    }

    public function variables(): array
    {
        return $this->variables;
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
