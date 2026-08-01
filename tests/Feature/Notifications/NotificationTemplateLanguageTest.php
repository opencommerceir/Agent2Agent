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
use App\Core\Application\Actions\SetTenantDefaultLanguageAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 Stage 4 (i18n) end-to-end: a tenant registers two translations
 * of the same NotificationTemplate (en + fa), notification.message.send
 * picks the one matching the detected Language, a missing translation
 * falls back to English, and a Tenant's own default_language reaches the
 * capability handler through AuthContext.language with no query/header at
 * all needed.
 */
class NotificationTemplateLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_withLangQueryParameter_rendersTheMatchingTranslation(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'notifications.templates.manage', 'notifications.messages.send', 'notifications.channels.manage',
        ]);
        $this->configureEmailChannel($token);

        $this->createTemplate($token, 'en', 'Your order is confirmed');
        $this->createTemplate($token, 'fa', 'سفارش شما تایید شد');

        $enResponse = $this->send($token, 'en');
        $enResponse->assertStatus(200);
        $this->assertSame('Your order is confirmed', $enResponse->json('data.notification.subject'));

        $faResponse = $this->send($token, 'fa');
        $faResponse->assertStatus(200);
        $this->assertSame('سفارش شما تایید شد', $faResponse->json('data.notification.subject'));
    }

    public function test_send_withNoFarsiTranslationConfigured_fallsBackToEnglish(): void
    {
        [, , $token] = $this->registerAgentWithPermissions([
            'notifications.templates.manage', 'notifications.messages.send', 'notifications.channels.manage',
        ]);
        $this->configureEmailChannel($token);

        $this->createTemplate($token, 'en', 'Your order is confirmed');

        $response = $this->send($token, 'fa');

        $response->assertStatus(200);
        $this->assertSame('Your order is confirmed', $response->json('data.notification.subject'));
    }

    public function test_send_withNoLanguageSignal_usesTenantDefaultLanguage(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'notifications.templates.manage', 'notifications.messages.send', 'notifications.channels.manage',
        ]);
        $this->configureEmailChannel($token);

        $this->createTemplate($token, 'fa', 'سفارش شما تایید شد');
        app(SetTenantDefaultLanguageAction::class)->execute($tenantId, 'fa');

        // No ?lang= query parameter, and 'Accept-Language' => '' explicitly
        // overrides Symfony's own Request::create() default of
        // "en-us,en;q=0.5" — simulating a bare Agent/API client that sends
        // no Accept-Language header at all, the case the Tenant-default
        // tier exists for.
        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.message.send',
            'input' => [
                'type' => 'order_placed',
                'channel' => 'email',
                'recipient' => 'ada@example.com',
                'variables' => [],
            ],
        ], ['Authorization' => "Bearer {$token}", 'Accept-Language' => '']);

        $response->assertStatus(200);
        $this->assertSame('سفارش شما تایید شد', $response->json('data.notification.subject'));
    }

    private function configureEmailChannel(string $token): void
    {
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'notification.channel.configure',
            'input' => ['channel' => 'email', 'config' => []],
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);
    }

    private function createTemplate(string $token, string $language, string $subject): void
    {
        $this->postJson('/mcp/v1/execute?lang='.$language, [
            'capability' => 'notification.template.create',
            'input' => [
                'type' => 'order_placed',
                'channel' => 'email',
                'subject_template' => $subject,
                'body_template' => 'Body',
                'language' => $language,
            ],
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);
    }

    private function send(string $token, string $language)
    {
        return $this->postJson('/mcp/v1/execute?lang='.$language, [
            'capability' => 'notification.message.send',
            'input' => [
                'type' => 'order_placed',
                'channel' => 'email',
                'recipient' => 'ada@example.com',
                'variables' => [],
            ],
        ], ['Authorization' => "Bearer {$token}"]);
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(NotificationsCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
