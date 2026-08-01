<?php

namespace App\Modules\Notifications;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Commerce\Domain\Events\OrderWasPlaced;
use App\Modules\Loyalty\Domain\Events\PointsWereEarned;
use App\Modules\Notifications\Application\Actions\ConfigureChannelAction;
use App\Modules\Notifications\Application\Actions\CreateTemplateAction;
use App\Modules\Notifications\Application\Actions\GetNotificationAction;
use App\Modules\Notifications\Application\Actions\GetTemplateAction;
use App\Modules\Notifications\Application\Actions\ListNotificationsAction;
use App\Modules\Notifications\Application\Actions\ListTemplatesAction;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Application\DTOs\NotificationChannelData;
use App\Modules\Notifications\Application\DTOs\NotificationTemplateData;
use App\Modules\Notifications\Application\Listeners\OrderPlacedNotificationListener;
use App\Modules\Notifications\Application\Listeners\PointsEarnedListener;
use App\Modules\Notifications\Application\Listeners\ShipmentStatusChangedListener;
use App\Modules\Notifications\Application\Services\ChannelSenderRegistry;
use App\Modules\Notifications\Application\Services\EmailSender;
use App\Modules\Notifications\Application\Services\InAppSender;
use App\Modules\Notifications\Application\Actions\SetUserPreferenceAction;
use App\Modules\Notifications\Application\Services\SmsSender;
use App\Modules\Notifications\Application\Services\WebhookSender;
use App\Modules\Notifications\Domain\Exceptions\TemplateNotFoundException;
use App\Modules\Notifications\Domain\Repositories\NotificationChannelRepositoryInterface;
use App\Modules\Notifications\Domain\Repositories\NotificationPreferenceRepositoryInterface;
use App\Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\Services\TemplateRenderer;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Infrastructure\Repositories\EloquentNotificationChannelRepository;
use App\Modules\Notifications\Infrastructure\Repositories\EloquentNotificationPreferenceRepository;
use App\Modules\Notifications\Infrastructure\Repositories\EloquentNotificationRepository;
use App\Modules\Notifications\Infrastructure\Repositories\EloquentNotificationTemplateRepository;
use App\Modules\Shipping\Domain\Events\ShipmentStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Notifications module — Phase 4, Stage 3, the first
 * genuinely cross-cutting module: it reacts to events dispatched by
 * Shipping, Commerce, *and* Loyalty (three source modules into one sink
 * module, all through their own published Repository Interfaces — the
 * same one-directional Module -> Module Dependency Inversion CRM/Finance/
 * Workflows/Loyalty already established).
 *
 * `ChannelSenderRegistry` mirrors Commerce's `ConnectorRegistry`/
 * Shipping's `ShippingProviderRegistry` — the third time this codebase
 * builds this exact in-memory-lookup-by-key shape.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows the
 * established seeder pattern instead (NotificationsCapabilitiesSeeder),
 * same RefreshDatabase-ordering reason documented there.
 */
class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(NotificationTemplateRepositoryInterface::class, EloquentNotificationTemplateRepository::class);
        $this->app->bind(NotificationChannelRepositoryInterface::class, EloquentNotificationChannelRepository::class);
        $this->app->bind(NotificationPreferenceRepositoryInterface::class, EloquentNotificationPreferenceRepository::class);

        $this->app->singleton(ChannelSenderRegistry::class);
    }

    public function boot(): void
    {
        $senders = $this->app->make(ChannelSenderRegistry::class);
        $senders->register(ChannelType::Email, $this->app->make(EmailSender::class));
        $senders->register(ChannelType::Sms, $this->app->make(SmsSender::class));
        $senders->register(ChannelType::Webhook, $this->app->make(WebhookSender::class));
        $senders->register(ChannelType::InApp, $this->app->make(InAppSender::class));

        Event::listen(ShipmentStatusChanged::class, ShipmentStatusChangedListener::class);
        Event::listen(OrderWasPlaced::class, OrderPlacedNotificationListener::class);
        Event::listen(PointsWereEarned::class, PointsEarnedListener::class);

        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('notification.message.send', function (array $input, AuthContext $context) {
            $type = NotificationType::from($input['type']);
            $channelType = ChannelType::from($input['channel']);
            $variables = $input['variables'] ?? [];

            $template = $this->app->make(NotificationTemplateRepositoryInterface::class)
                ->findActive($context->tenantId, $type, $channelType, $context->language);

            if (! $template) {
                throw new TemplateNotFoundException(
                    "No active NotificationTemplate for type [{$input['type']}] on channel [{$input['channel']}]."
                );
            }

            $renderer = $this->app->make(TemplateRenderer::class);

            $result = $this->app->make(SendNotificationAction::class)->execute(
                tenantId: $context->tenantId,
                type: $type,
                channelType: $channelType,
                recipient: new Recipient($input['recipient']),
                subject: $renderer->render($template->subjectTemplate(), $variables),
                body: $renderer->render($template->bodyTemplate(), $variables),
                templateId: $template->id(),
            );

            // null means the channel isn't configured/active for this
            // tenant — a direct send has no Preference to skip on
            // (SendNotificationAction's own docblock), so null here only
            // ever means "channel not configured yet."
            return ['notification' => $result?->toArray()];
        });

        $handlers->register('notification.template.create', function (array $input, AuthContext $context) {
            /** @var NotificationTemplateData $template */
            $template = $this->app->make(CreateTemplateAction::class)->execute(
                tenantId: $context->tenantId,
                type: $input['type'],
                channelType: $input['channel'],
                subjectTemplate: $input['subject_template'],
                bodyTemplate: $input['body_template'],
                variables: $input['variables'] ?? [],
                // Optional, omitted from inputSchema (HANDOFF §3 pattern
                // #7) — defaults to 'en'. Registering a second language for
                // the same type+channel is calling this capability again
                // with a different `language`, not a nested payload.
                language: $input['language'] ?? 'en',
            );

            return ['template' => $template->toArray()];
        });

        $handlers->register('notification.template.get', function (array $input, AuthContext $context) {
            $template = $this->app->make(GetTemplateAction::class)->execute((int) $input['template_id'], $context->tenantId);

            return ['template' => $template->toArray()];
        });

        $handlers->register(
            'notification.template.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListTemplatesAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('notification.channel.configure', function (array $input, AuthContext $context) {
            /** @var NotificationChannelData $channel */
            $channel = $this->app->make(ConfigureChannelAction::class)->execute(
                tenantId: $context->tenantId,
                channelType: $input['channel'],
                config: $input['config'] ?? [],
                isActive: $input['is_active'] ?? true,
            );

            return ['channel' => $channel->toArray()];
        });

        $handlers->register('notification.message.get', function (array $input, AuthContext $context) {
            $notification = $this->app->make(GetNotificationAction::class)->execute((int) $input['notification_id'], $context->tenantId);

            return ['notification' => $notification->toArray()];
        });

        $handlers->register(
            'notification.message.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListNotificationsAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('notification.preference.set', function (array $input, AuthContext $context) {
            $preference = $this->app->make(SetUserPreferenceAction::class)->execute(
                tenantId: $context->tenantId,
                recipientType: $input['recipient_type'],
                recipientId: (int) $input['recipient_id'],
                notificationType: $input['notification_type'],
                channelType: $input['channel'],
                isEnabled: $input['is_enabled'],
            );

            return ['preference' => $preference->toArray()];
        });
    }
}
