<?php

namespace Tests\Feature\Notifications;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateSubscriptionPlanAction;
use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use App\Modules\Commerce\Domain\Events\SubscriptionPaymentFailed;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The real, no-event-faking path SubscriptionPaymentFailedListener
 * (registered in NotificationsServiceProvider::boot(), never called
 * directly by this test) reacts to: a real SubscriptionPaymentFailed event
 * dispatched -> Customer resolved via CustomerRepositoryInterface ->
 * active subscription_payment_failed/email Template found -> rendered ->
 * SendNotificationAction runs -> a real Notification row created. Same
 * "no event faking" style NotificationCapabilityTest already uses for
 * ShipmentStatusChangedListener.
 */
class SubscriptionPaymentFailedListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_paymentFailedEvent_withActiveTemplate_createsRenderedNotification(): void
    {
        $this->seed(NotificationsCapabilitiesSeeder::class);

        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'notifications.templates.manage', 'notifications.channels.manage', 'notifications.messages.read',
        ]);

        $createTemplate = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.template.create',
            'input' => [
                'type' => 'subscription_payment_failed',
                'channel' => 'email',
                'subject_template' => 'Payment failed for subscription {{subscription_id}}',
                'body_template' => 'Hi {{customer_name}}, your payment of {{amount}} failed (attempt {{retry_count}}).',
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $createTemplate->assertStatus(200);

        $configureChannel = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.channel.configure',
            'input' => ['channel' => 'email', 'config' => ['from' => 'noreply@example.com']],
        ], ['Authorization' => "Bearer {$token}"]);
        $configureChannel->assertStatus(200);

        $customerEmail = 'ada-'.uniqid().'@example.com';
        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Ada', 'Lovelace', $customerEmail);
        $plan = app(CreateSubscriptionPlanAction::class)->execute(
            tenantId: $tenantId,
            name: 'Pro Plan',
            description: null,
            billingCycle: 'monthly',
            priceAmount: 2999,
            priceCurrency: 'USD',
            trialDays: 0,
        );

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customer->id,
            subscriptionPlanId: $plan->id,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: new DateTimeImmutable('+1 month'),
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), Money::fromAmount(2999, 'USD'), new DateTimeImmutable());
        $invoice->markFailed();
        $invoice = app(SubscriptionInvoiceRepositoryInterface::class)->save($invoice);

        // The real event — no Event::fake() anywhere in this test.
        event(new SubscriptionPaymentFailed($subscription, $invoice));

        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.message.list',
            'input' => ['type' => 'subscription_payment_failed'],
        ], ['Authorization' => "Bearer {$token}"]);
        $list->assertStatus(200);

        $notifications = $list->json('data.notifications');
        $this->assertCount(1, $notifications);
        $this->assertSame('sent', $notifications[0]['status']);
        $this->assertSame("Payment failed for subscription {$subscription->id()}", $notifications[0]['subject']);
        $this->assertStringContainsString('Ada Lovelace', $notifications[0]['body']);
        $this->assertStringContainsString('29.99', $notifications[0]['body']);
        $this->assertStringContainsString('attempt 1', $notifications[0]['body']);
        $this->assertSame($customerEmail, $notifications[0]['recipient']);
    }

    public function test_paymentFailedEvent_withoutConfiguredTemplate_createsNoNotificationAndDoesNotThrow(): void
    {
        $this->seed(NotificationsCapabilitiesSeeder::class);

        [$tenantId] = $this->registerAgentWithPermissions([]);

        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Bob', 'Builder', 'bob-'.uniqid().'@example.com');
        $plan = app(CreateSubscriptionPlanAction::class)->execute(
            tenantId: $tenantId,
            name: 'Pro Plan',
            description: null,
            billingCycle: 'monthly',
            priceAmount: 1999,
            priceCurrency: 'USD',
            trialDays: 0,
        );

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customer->id,
            subscriptionPlanId: $plan->id,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: new DateTimeImmutable('+1 month'),
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), Money::fromAmount(1999, 'USD'), new DateTimeImmutable());
        $invoice->markFailed();
        $invoice = app(SubscriptionInvoiceRepositoryInterface::class)->save($invoice);

        // No active subscription_payment_failed/email Template exists for
        // this tenant — the Listener must return silently, never throw.
        event(new SubscriptionPaymentFailed($subscription, $invoice));

        $this->assertSame(0, DB::table('notifications')->where('tenant_id', $tenantId)->count());
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
