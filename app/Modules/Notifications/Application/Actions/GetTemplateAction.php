<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Application\DTOs\NotificationTemplateData;
use App\Modules\Notifications\Domain\Exceptions\TemplateNotFoundException;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;

final class GetTemplateAction
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
    ) {
    }

    public function execute(int $templateId, int $tenantId): NotificationTemplateData
    {
        $template = $this->templates->findById($templateId, $tenantId);

        if (! $template) {
            throw new TemplateNotFoundException("NotificationTemplate [{$templateId}] does not exist.");
        }

        return NotificationTemplateData::fromEntity($template);
    }
}
