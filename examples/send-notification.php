<?php

/**
 * A minimal AI Agent script demonstrating Phase 4 Stage 3's Notifications
 * module — the same `opencommerce/sdk` path examples/shipping-provider.php
 * demonstrates, applied to a direct send:
 *   - notification.channel.configure
 *   - notification.template.create
 *   - notification.message.send
 *   - notification.message.get
 *
 * Prerequisites:
 *   1. `php artisan serve` running this app (default: http://localhost:8000).
 *   2. An Agent token with `notifications.channels.manage`,
 *      `notifications.templates.manage`, `notifications.messages.send`,
 *      and `notifications.messages.read` — see
 *      packages/opencommerce-sdk/README.md's "Quick Start" section.
 *   3. `notification.message.send` requires an active Template for the
 *      given type+channel to already exist (it renders `variables`
 *      through that Template, per NotificationsServiceProvider's own
 *      handler) — this script creates one before sending, so it works
 *      standalone.
 *
 * Usage:
 *   php examples/send-notification.php <token> [recipient-email] [base-url]
 */

require __DIR__.'/../vendor/autoload.php';

use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\Exceptions\MCPException;
use OpenCommerce\SDK\MCPClient;

$token = $argv[1] ?? null;
$recipient = $argv[2] ?? 'customer@example.com';
$baseUrl = $argv[3] ?? 'http://localhost:8000/mcp/v1';

if (! $token) {
    fwrite(STDERR, "Usage: php examples/send-notification.php <token> [recipient-email] [base-url]\n");
    exit(1);
}

$config = new MCPConfig(baseUrl: $baseUrl, token: $token);
$client = new MCPClient($config);

echo "=== notification.channel.configure ===\n";
try {
    $result = $client->execute('notification.channel.configure', [
        'channel' => 'email',
        'config' => ['from' => 'noreply@example.com'],
    ]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Channel configure failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

echo "\n=== notification.template.create ===\n";
try {
    $result = $client->execute('notification.template.create', [
        'type' => 'order_placed',
        'channel' => 'email',
        'subject_template' => 'Thanks for your order, {{customer_name}}!',
        'body_template' => 'Your order {{order_number}} has been received.',
    ]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Template create failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

echo "\n=== notification.message.send ===\n";
try {
    $result = $client->execute('notification.message.send', [
        'type' => 'order_placed',
        'channel' => 'email',
        'recipient' => $recipient,
        'variables' => ['customer_name' => 'Ada', 'order_number' => 'ORD-20260801-00001'],
    ]);
    print_r($result->getData());
    $notificationId = $result->getData()['notification']['id'] ?? null;
} catch (MCPException $e) {
    fwrite(STDERR, "Send failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

if ($notificationId === null) {
    echo "\nNotification wasn't sent (channel not active) — nothing to look up.\n";
    exit(0);
}

echo "\n=== notification.message.get ===\n";
try {
    $result = $client->execute('notification.message.get', ['notification_id' => $notificationId]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Lookup failed: [{$e->errorCode}] {$e->getMessage()}\n");
}
