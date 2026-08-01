<?php

namespace App\Modules\Notifications\Interfaces\MCP;

/**
 * The capability manifest for the Notifications module — what
 * NotificationsCapabilitiesSeeder registers into the Capability Registry
 * and NotificationsServiceProvider wires into CapabilityHandlerRegistry.
 * Kept as plain data here, separate from the seeder's idempotency
 * plumbing, the same split every prior module's own capability manifest
 * established.
 *
 * 3 of the 8 requested names were 2 dot-separated segments —
 * `notification.send`, `notification.get`, `notification.list` —
 * CapabilityName requires exactly 3 (HANDOFF gotcha #2, hit again the
 * same way it's hit in every module except Loyalty/Reporting). Renamed
 * to `notification.message.send/get/list` (a sent Notification is
 * fundamentally "a message"). Two requested permissions,
 * `notifications.send`/`notifications.read`, were the same problem for
 * PermissionKey — renamed to `notifications.messages.send`/
 * `notifications.messages.read`.
 */
final class NotificationsCapabilities
{
    /**
     * @return list<array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, string>,
     *     outputSchema: array<string, string>,
     *     requiredPermissions: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'notification.message.send',
                'description' => 'Render the active Template for a type+channel with the given variables and send it directly to a raw recipient (email/phone/URL) — no Preference check, since there is no recipient id to check one against',
                'inputSchema' => ['type' => 'string', 'recipient' => 'string', 'channel' => 'string', 'variables' => 'object'],
                'outputSchema' => ['notification' => 'array'],
                'requiredPermissions' => ['notifications.messages.send'],
            ],
            [
                'name' => 'notification.template.create',
                'description' => 'Create a NotificationTemplate: a subject/body pair with {{variable}} placeholders for a given type+channel',
                'inputSchema' => ['type' => 'string', 'channel' => 'string', 'subject_template' => 'string', 'body_template' => 'string'],
                'outputSchema' => ['template' => 'array'],
                'requiredPermissions' => ['notifications.templates.manage'],
            ],
            [
                'name' => 'notification.template.get',
                'description' => 'Get a NotificationTemplate by id',
                'inputSchema' => ['template_id' => 'integer'],
                'outputSchema' => ['template' => 'array'],
                'requiredPermissions' => ['notifications.templates.read'],
            ],
            [
                'name' => 'notification.template.list',
                'description' => "List the tenant's NotificationTemplates, optionally filtered by type or channel",
                // type and channel are both optional.
                'inputSchema' => [],
                'outputSchema' => ['templates' => 'array'],
                'requiredPermissions' => ['notifications.templates.read'],
            ],
            [
                'name' => 'notification.channel.configure',
                'description' => 'Configure (create or update) a tenant\'s NotificationChannel settings',
                // is_active is optional, defaults to true.
                'inputSchema' => ['channel' => 'string', 'config' => 'object'],
                'outputSchema' => ['channel' => 'array'],
                'requiredPermissions' => ['notifications.channels.manage'],
            ],
            [
                'name' => 'notification.message.get',
                'description' => 'Get a sent Notification by id',
                'inputSchema' => ['notification_id' => 'integer'],
                'outputSchema' => ['notification' => 'array'],
                'requiredPermissions' => ['notifications.messages.read'],
            ],
            [
                'name' => 'notification.message.list',
                'description' => "List the tenant's sent Notifications, optionally filtered by type or status",
                // type, status, and limit are all optional.
                'inputSchema' => [],
                'outputSchema' => ['notifications' => 'array'],
                'requiredPermissions' => ['notifications.messages.read'],
            ],
            [
                'name' => 'notification.preference.set',
                'description' => 'Enable or disable one notification type+channel combination for a recipient',
                'inputSchema' => [
                    'recipient_type' => 'string',
                    'recipient_id' => 'integer',
                    'notification_type' => 'string',
                    'channel' => 'string',
                    'is_enabled' => 'boolean',
                ],
                'outputSchema' => ['preference' => 'array'],
                'requiredPermissions' => ['notifications.preferences.manage'],
            ],
        ];
    }
}
