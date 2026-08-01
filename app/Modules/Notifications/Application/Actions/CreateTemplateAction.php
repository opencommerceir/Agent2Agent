<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Core\Domain\ValueObjects\Language;
use App\Modules\Notifications\Application\DTOs\NotificationTemplateData;
use App\Modules\Notifications\Domain\Entities\NotificationTemplate;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;

final class CreateTemplateAction
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
    ) {
    }

    public function execute(
        int $tenantId,
        string $type,
        string $channelType,
        string $subjectTemplate,
        string $bodyTemplate,
        array $variables = [],
        string $language = 'en',
    ): NotificationTemplateData {
        $template = NotificationTemplate::create(
            tenantId: $tenantId,
            type: NotificationType::from($type),
            channelType: ChannelType::from($channelType),
            subjectTemplate: $subjectTemplate,
            bodyTemplate: $bodyTemplate,
            variables: $variables,
            language: Language::from($language),
        );

        $template = $this->templates->save($template);

        return NotificationTemplateData::fromEntity($template);
    }
}
