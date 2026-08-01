<?php

namespace App\Modules\Notifications\Application\DTOs;

use App\Modules\Notifications\Domain\Entities\NotificationTemplate;

final class NotificationTemplateData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $type,
        public readonly string $channelType,
        public readonly string $subjectTemplate,
        public readonly string $bodyTemplate,
        public readonly array $variables,
        public readonly bool $isActive,
        public readonly string $language,
    ) {
    }

    public static function fromEntity(NotificationTemplate $template): self
    {
        return new self(
            id: $template->id(),
            tenantId: $template->tenantId(),
            type: $template->type()->value,
            channelType: $template->channelType()->value,
            subjectTemplate: $template->subjectTemplate(),
            bodyTemplate: $template->bodyTemplate(),
            variables: $template->variables(),
            isActive: $template->isActive(),
            language: $template->language()->value,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'type' => $this->type,
            'channelType' => $this->channelType,
            'subjectTemplate' => $this->subjectTemplate,
            'bodyTemplate' => $this->bodyTemplate,
            'variables' => $this->variables,
            'isActive' => $this->isActive,
            'language' => $this->language,
        ];
    }
}
