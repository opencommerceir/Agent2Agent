<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Core\Application\Services\LanguageDetector;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\Services\TemplateRenderer;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;
use App\Modules\Shipping\Domain\Events\ShipmentStatusChanged;

/**
 * Reacts to Shipping's ShipmentStatusChanged — depends on Commerce's
 * OrderRepositoryInterface/CustomerRepositoryInterface (Interfaces, never
 * Commerce's Infrastructure/Model classes), the same Dependency Inversion
 * direction every cross-module Listener in this codebase already
 * establishes (InventoryLowListener/CartAbandonedListener). Skips
 * silently — never throws — when the Order has no linked Customer
 * (nullable since Commerce Stage 4) or no active
 * `shipment_status_changed`/`email` Template is configured for the
 * tenant: both are genuinely normal states, not errors. Variables:
 * `{{order_number}}`, `{{tracking_number}}`, `{{status}}`,
 * `{{customer_name}}`.
 *
 * Language (Phase 4 Stage 4, i18n): a Listener reacts to a Domain Event,
 * not an HTTP request, so there's no query/header to detect from — it uses
 * LanguageDetector::detectForTenant(), the Tenant-default-or-English tier
 * only. No per-Customer language preference exists in this codebase yet
 * (§9 candidate); this is the same signal every other language-aware call
 * site in this module uses until one does.
 */
final class ShipmentStatusChangedListener
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CustomerRepositoryInterface $customers,
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly TemplateRenderer $renderer,
        private readonly SendNotificationAction $sendNotification,
        private readonly LanguageDetector $languageDetector,
    ) {
    }

    public function handle(ShipmentStatusChanged $event): void
    {
        $shipment = $event->shipment;
        $order = $this->orders->findById($shipment->orderId(), $shipment->tenantId());

        if (! $order || $order->customerId() === null) {
            return;
        }

        $customer = $this->customers->findById($order->customerId(), $shipment->tenantId());

        if (! $customer) {
            return;
        }

        $template = $this->templates->findActive(
            $shipment->tenantId(),
            NotificationType::ShipmentStatusChanged,
            ChannelType::Email,
            $this->languageDetector->detectForTenant($shipment->tenantId()),
        );

        if (! $template) {
            return;
        }

        $variables = [
            'order_number' => $order->orderNumber()->value(),
            'tracking_number' => $shipment->trackingNumber()->value(),
            'status' => $shipment->status()->value,
            'customer_name' => $customer->fullName(),
        ];

        $this->sendNotification->execute(
            tenantId: $shipment->tenantId(),
            type: NotificationType::ShipmentStatusChanged,
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
