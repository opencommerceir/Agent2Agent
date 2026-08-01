<?php

namespace Tests\Feature\Notifications;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Notifications\Application\Actions\ConfigureChannelAction;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Application\Actions\SetUserPreferenceAction;
use App\Modules\Notifications\Application\Services\ChannelSenderRegistry;
use App\Modules\Notifications\Domain\Exceptions\ChannelSendFailedException;
use App\Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use App\Modules\Notifications\Domain\Services\ChannelSenderInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SendNotificationAction directly — the retry/backoff owner (that
 * Action's own docblock has the full reasoning). Uses a small stub
 * ChannelSenderInterface re-registered directly into ChannelSenderRegistry
 * (the same "re-register a fresh instance" technique
 * SyncWooCommerceProductsTest/ShippingProviderCapabilityTest already use
 * for their own registries) so the retry count is actually observable,
 * not just the eventual outcome.
 */
class SendNotificationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withNoChannelConfigured_returnsNullAndPersistsNothing(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $result = app(SendNotificationAction::class)->execute(
            tenantId: $tenant->id,
            type: NotificationType::OrderPlaced,
            channelType: ChannelType::Email,
            recipient: new Recipient('customer@example.com'),
            subject: 'Subject',
            body: 'Body',
        );

        $this->assertNull($result);
    }

    public function test_execute_withDisabledPreference_returnsNull(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(ConfigureChannelAction::class)->execute($tenant->id, 'email', []);
        app(SetUserPreferenceAction::class)->execute(
            $tenant->id, 'customer', 1, 'order_placed', 'email', false,
        );

        $result = app(SendNotificationAction::class)->execute(
            tenantId: $tenant->id,
            type: NotificationType::OrderPlaced,
            channelType: ChannelType::Email,
            recipient: new Recipient('customer@example.com'),
            subject: 'Subject',
            body: 'Body',
            recipientType: RecipientType::Customer,
            recipientId: 1,
        );

        $this->assertNull($result);
    }

    public function test_execute_whenSenderFailsTwiceThenSucceeds_retriesAndMarksSent(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(ConfigureChannelAction::class)->execute($tenant->id, 'sms', []);

        $stub = $this->flakySender(failuresBeforeSuccess: 2);
        app(ChannelSenderRegistry::class)->register(ChannelType::Sms, $stub);

        $result = app(SendNotificationAction::class)->execute(
            tenantId: $tenant->id,
            type: NotificationType::OrderPlaced,
            channelType: ChannelType::Sms,
            recipient: new Recipient('+15551234567'),
            subject: 'Subject',
            body: 'Body',
        );

        $this->assertNotNull($result);
        $this->assertSame('sent', $result->status);
        $this->assertSame(3, $stub->attempts);
    }

    public function test_execute_whenSenderAlwaysFails_marksFailedAfterThreeAttemptsWithoutThrowing(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(ConfigureChannelAction::class)->execute($tenant->id, 'sms', []);

        $stub = $this->flakySender(failuresBeforeSuccess: 999);
        app(ChannelSenderRegistry::class)->register(ChannelType::Sms, $stub);

        $result = app(SendNotificationAction::class)->execute(
            tenantId: $tenant->id,
            type: NotificationType::OrderPlaced,
            channelType: ChannelType::Sms,
            recipient: new Recipient('+15551234567'),
            subject: 'Subject',
            body: 'Body',
        );

        $this->assertNotNull($result);
        $this->assertSame('failed', $result->status);
        $this->assertNotNull($result->errorMessage);
        $this->assertSame(3, $stub->attempts);

        $saved = app(NotificationRepositoryInterface::class)->findById($result->id, $tenant->id);
        $this->assertSame('failed', $saved->status()->value);
    }

    private function flakySender(int $failuresBeforeSuccess): ChannelSenderInterface
    {
        return new class($failuresBeforeSuccess) implements ChannelSenderInterface
        {
            public int $attempts = 0;

            public function __construct(private readonly int $failuresBeforeSuccess)
            {
            }

            public function send(string $recipient, string $subject, string $body): void
            {
                $this->attempts++;

                if ($this->attempts <= $this->failuresBeforeSuccess) {
                    throw new ChannelSendFailedException("Simulated failure #{$this->attempts}.");
                }
            }
        };
    }
}
