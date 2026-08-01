<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Commerce\Domain\Events\OrderWasPlaced;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\Services\TemplateRenderer;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;

/**
 * Reacts to Commerce's own OrderWasPlaced (carries the full Order — no
 * OrderRepositoryInterface lookup needed, unlike ShipmentStatusChangedListener,
 * since the event already carries everything). Same Customer lookup /
 * silent-skip shape.
 */
final class OrderPlacedNotificationListener
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly TemplateRenderer $renderer,
        private readonly SendNotificationAction $sendNotification,
    ) {
    }

    public function handle(OrderWasPlaced $event): void
    {
        $order = $event->order;

        if ($order->customerId() === null) {
            return;
        }

        $customer = $this->customers->findById($order->customerId(), $order->tenantId());

        if (! $customer) {
            return;
        }

        $template = $this->templates->findActive($order->tenantId(), NotificationType::OrderPlaced, ChannelType::Email);

        if (! $template) {
            return;
        }

        $variables = [
            'order_number' => $order->orderNumber()->value(),
            'customer_name' => $customer->fullName(),
        ];

        $this->sendNotification->execute(
            tenantId: $order->tenantId(),
            type: NotificationType::OrderPlaced,
            channelType: ChannelType::Email,
            recipient: new Recipient($customer->email()->value()),
            subject: $this->renderer->render($template->subjectTemplate(), $variables),
            body: $this->renderer->render($template->bodyTemplate(), $variables),
            templateId: $template->id(),
            recipientType: RecipientType::Customer,
            recipientId: $customer->id(),
        );
    }
}
