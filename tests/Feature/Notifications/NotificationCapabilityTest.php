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
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ShippingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal 13-step end-to-end scenario from this stage's own request:
 * Template -> Email Channel -> Customer -> Preference enabled -> real
 * Order + Shipment -> a real status change -> Shipping's real
 * ShipmentStatusChanged fires -> ShipmentStatusChangedListener (registered
 * in NotificationsServiceProvider::boot(), never called directly by this
 * test) reacts -> SendNotificationAction runs -> Notification sent,
 * subject rendered -> Preference disabled -> status changes again -> no
 * new Notification -> tenant isolation -> filtered list.
 */
class NotificationCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullShipmentNotificationScenario(): void
    {
        // No Mail::fake() needed — phpunit.xml already sets
        // MAIL_MAILER=array, so EmailSender's Mail::raw() call is
        // captured in-memory, never touching a real network.
        $this->seed(NotificationsCapabilitiesSeeder::class);
        $this->seed(ShippingCapabilitiesSeeder::class);

        [$tenantA, $agentA, $tokenA] = $this->registerAgentWithPermissions([
            'notifications.templates.manage', 'notifications.channels.manage',
            'notifications.preferences.manage', 'notifications.messages.read',
            'shipping.methods.create', 'shipping.shipments.create', 'shipping.shipments.update',
        ]);

        // Step 1: a Template for shipment_status_changed/email.
        $createTemplate = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.template.create',
            'input' => [
                'type' => 'shipment_status_changed',
                'channel' => 'email',
                'subject_template' => 'Your order {{order_number}} is now {{status}}',
                'body_template' => 'Hi {{customer_name}}, your shipment {{tracking_number}} is now {{status}}.',
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $createTemplate->assertStatus(200);

        // Step 2: configure the Email channel, active.
        $configureChannel = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.channel.configure',
            'input' => ['channel' => 'email', 'config' => ['from' => 'noreply@example.com']],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $configureChannel->assertStatus(200);
        $this->assertTrue($configureChannel->json('data.channel.isActive'));

        // Step 3: a Customer with an email.
        $customer = app(CreateCustomerAction::class)->execute($tenantA, 'Ada', 'Lovelace', 'ada@example.com');

        // Step 4: the Customer's preference for shipment_status_changed/email is enabled.
        $enablePreference = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.preference.set',
            'input' => [
                'recipient_type' => 'customer', 'recipient_id' => $customer->id,
                'notification_type' => 'shipment_status_changed', 'channel' => 'email',
                'is_enabled' => true,
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $enablePreference->assertStatus(200);

        // Step 5: a real Order + Shipment for that Customer.
        $product = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'SKU-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenantA, MemberType::Agent, $agentA, $product->id, 1);
        $order = app(PlaceOrderAction::class)->execute(
            tenantId: $tenantA, agentId: $agentA, cartId: $cart->id, customerId: $customer->id,
        );

        $method = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.method.create',
            'input' => ['name' => 'Standard', 'base_rate' => 500, 'rate_per_kg' => 100, 'estimated_days_min' => 2, 'estimated_days_max' => 5],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $methodId = $method->json('data.method.id');

        $shipment = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.create',
            'input' => ['order_id' => $order->id, 'shipping_method_id' => $methodId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $shipmentId = $shipment->json('data.shipment.id');

        // Steps 6-8: a real status change -> ShipmentStatusChanged fires ->
        // ShipmentStatusChangedListener reacts -> SendNotificationAction runs.
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.transition',
            'input' => ['shipment_id' => $shipmentId, 'status' => 'in_transit'],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        // Step 9: a Notification exists — sent, subject rendered.
        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.message.list',
            'input' => ['type' => 'shipment_status_changed'],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data.notifications'));
        $notification = $list->json('data.notifications.0');
        $this->assertSame('sent', $notification['status']);
        $this->assertSame("Your order {$order->orderNumber} is now in_transit", $notification['subject']);
        $this->assertSame('ada@example.com', $notification['recipient']);
        $notificationId = $notification['id'];

        // Step 10: the Customer disables the preference.
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.preference.set',
            'input' => [
                'recipient_type' => 'customer', 'recipient_id' => $customer->id,
                'notification_type' => 'shipment_status_changed', 'channel' => 'email',
                'is_enabled' => false,
            ],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        // Step 11: status changes again -> no new Notification.
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.transition',
            'input' => ['shipment_id' => $shipmentId, 'status' => 'delivered'],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        $listAfterDisable = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.message.list',
            'input' => ['type' => 'shipment_status_changed'],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $this->assertCount(1, $listAfterDisable->json('data.notifications')); // still just the one from step 9

        // Step 12: Tenant B's Agent cannot see Tenant A's Notification.
        [, , $tokenB] = $this->registerAgentWithPermissions(['notifications.messages.read']);

        $crossTenant = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.message.get',
            'input' => ['notification_id' => $notificationId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenant->assertStatus(404);
        $crossTenant->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 13: list filtered by status.
        $filteredList = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.message.list',
            'input' => ['status' => 'sent'],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $filteredList->assertStatus(200);
        $this->assertCount(1, $filteredList->json('data.notifications'));
    }

    public function test_createTemplate_withoutPermission_returnsForbidden(): void
    {
        $this->seed(NotificationsCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.template.create',
            'input' => ['type' => 'order_placed', 'channel' => 'email', 'subject_template' => 'X', 'body_template' => 'Y'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
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
