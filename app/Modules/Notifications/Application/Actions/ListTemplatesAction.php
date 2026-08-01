<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Application\DTOs\NotificationTemplateData;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;

final class ListTemplatesAction
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{templates: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $type = isset($input['type']) ? NotificationType::from($input['type']) : null;
        $channelType = isset($input['channel']) ? ChannelType::from($input['channel']) : null;

        $templates = $this->templates->list($tenantId, $type, $channelType);

        return [
            'templates' => array_map(fn ($template) => NotificationTemplateData::fromEntity($template)->toArray(), $templates),
        ];
    }
}
