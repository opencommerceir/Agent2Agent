<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Core\Application\Services\LanguageDetector;
use App\Modules\Commerce\Domain\Events\SubscriptionPaymentFailed;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\Repositories\NotificationTemplateRepositoryInterface;
use App\Modules\Notifications\Domain\Services\TemplateRenderer;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;

/**
 * Reacts to Commerce's SubscriptionPaymentFailed — depends on Commerce's
 * CustomerRepositoryInterface (an Interface, never Commerce's
 * Infrastructure/Model classes), the same Dependency Inversion direction
 * every cross-module Listener in this codebase already establishes
 * (ShipmentStatusChangedListener/InventoryLowListener/CartAbandonedListener).
 * Skips silently — never throws — when the Subscription has no resolvable
 * Customer or no active `subscription_payment_failed`/`email` Template is
 * configured for the tenant: both are genuinely normal states, not errors.
 * Fires on *every* failed charge attempt (first failure and every retry,
 * not just the final one) — see SubscriptionPaymentFailed's own docblock.
 * Variables: `{{subscription_id}}`, `{{amount}}`, `{{retry_count}}`,
 * `{{customer_name}}`.
 *
 * Language (Phase 4 Stage 4, i18n): a Listener reacts to a Domain Event,
 * not an HTTP request, so there's no query/header to detect from — it uses
 * LanguageDetector::detectForTenant(), the same signal
 * ShipmentStatusChangedListener already uses.
 */
final class SubscriptionPaymentFailedListener
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly TemplateRenderer $renderer,
        private readonly SendNotificationAction $sendNotification,
        private readonly LanguageDetector $languageDetector,
    ) {
    }

    public function handle(SubscriptionPaymentFailed $event): void
    {
        $subscription = $event->subscription;
        $invoice = $event->invoice;

        $customer = $this->customers->findById($subscription->customerId(), $subscription->tenantId());

        if (! $customer) {
            return;
        }

        $template = $this->templates->findActive(
            $subscription->tenantId(),
            NotificationType::SubscriptionPaymentFailed,
            ChannelType::Email,
            $this->languageDetector->detectForTenant($subscription->tenantId()),
        );

        if (! $template) {
            return;
        }

        $variables = [
            'subscription_id' => (string) $subscription->id(),
            'amount' => (string) $invoice->amount(),
            'retry_count' => (string) $invoice->retryCount(),
            'customer_name' => $customer->fullName(),
        ];

        $this->sendNotification->execute(
            tenantId: $subscription->tenantId(),
            type: NotificationType::SubscriptionPaymentFailed,
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
