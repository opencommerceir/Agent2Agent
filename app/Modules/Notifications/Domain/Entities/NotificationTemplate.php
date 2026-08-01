<?php

namespace App\Modules\Notifications\Domain\Entities;

use App\Core\Domain\ValueObjects\Language;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use DateTimeImmutable;

/**
 * A reusable, tenant-owned subject/body pair with `{{variable}}`
 * placeholders (`TemplateRenderer` is the one place these get rendered).
 * `variables` is a plain documentation list of the names a caller may
 * substitute — nothing enforces every listed name actually appears in
 * either template string, or vice versa.
 *
 * $language (Phase 4 Stage 4, i18n) means a tenant registers one
 * NotificationTemplate row per Language it wants to support for the same
 * type+channel — not a single row holding a nested translations map, the
 * shape this stage's own request illustrated. Keeping one language per row
 * is the same "widen with an optional trailing field" shape (HANDOFF §3
 * pattern #6) every prior extension of an existing Entity in this codebase
 * has used, and needs no restructuring of subjectTemplate/bodyTemplate,
 * NotificationTemplateData, or the Eloquent mapping — registering a second
 * language is just calling notification.template.create again with a
 * different `language` input. See
 * EloquentNotificationTemplateRepository::findActive()'s own docblock for
 * how the fallback-to-English rule works across these rows.
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
        private readonly Language $language = Language::English,
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
        Language $language = Language::English,
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
            language: $language,
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

    public function language(): Language
    {
        return $this->language;
    }
}
