<?php

namespace Tests\Feature\AgentOrchestrator;

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
use App\Modules\Notifications\Application\Actions\CreateTemplateAction;
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CEO Agent, specifically: proves the real `config/agents/ceo.php`
 * profile is what actually drives a run — not just "steps completed"
 * (GoalExecutionTest's own focus), but that the real config-declared
 * `default_inputs` templates were genuinely resolved and used as each
 * capability's own input.
 */
class CEOAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ceoProfileDrivesRealCapabilityInputFromConfig(): void
    {
        [$tenantId, , $token] = $this->registerCeoAgent();
        $this->seedPromotionTemplate($tenantId);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 20% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $steps = collect($response->json('steps'))->keyBy('capability');

        // report.sales.generate: config/agents/ceo.php declares
        // {date:-7}/{date:0} — resolved to real Y-m-d strings.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $steps['report.sales.generate']['input']['start_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $steps['report.sales.generate']['input']['end_date']);

        // analytics.kpi.calculate: config declares a literal kpi_type
        // ('revenue') — passed through untouched, no token to resolve.
        $this->assertSame('revenue', $steps['analytics.kpi.calculate']['input']['kpi_type']);

        // commerce.coupon.create: {coupon_code} -> a real COUPON-XXXXX,
        // {discount_percent} -> parsed from this test's own goal text (20%).
        $this->assertMatchesRegularExpression('/^COUPON-[A-Z0-9]{5}$/', $steps['commerce.coupon.create']['input']['code']);
        $this->assertSame(20, $steps['commerce.coupon.create']['input']['discount_value']);
        $this->assertSame('percentage', $steps['commerce.coupon.create']['input']['discount_type']);

        // notification.message.send: literal config values, unresolved.
        $this->assertSame('promotion_announcement', $steps['notification.message.send']['input']['type']);
        $this->assertSame('email', $steps['notification.message.send']['input']['channel']);
    }

    public function test_ceoProfileRevenueRuleIsUsedForARevenueGoal(): void
    {
        [, , $token] = $this->registerCeoAgent();

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Review our revenue this quarter',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertSame(
            ['report.revenue.generate', 'analytics.kpi.calculate'],
            array_column($response->json('steps'), 'capability'),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerCeoAgent(): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        $this->seed(ReportingCapabilitiesSeeder::class);
        $this->seed(AnalyticsCapabilitiesSeeder::class);
        $this->seed(NotificationsCapabilitiesSeeder::class);
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'CEO Agent', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'CEO', 'ceo-'.uniqid());

        foreach ([
            'agent.goals.execute', 'reporting.sales.read', 'reporting.revenue.read',
            'analytics.kpis.read', 'commerce.coupons.create', 'notifications.messages.send',
            'notifications.templates.manage',
        ] as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }

    private function seedPromotionTemplate(int $tenantId): void
    {
        app(CreateTemplateAction::class)->execute(
            tenantId: $tenantId,
            type: 'promotion_announcement',
            channelType: 'email',
            subjectTemplate: '{{discount_percent}}% off this week',
            bodyTemplate: 'Enjoy {{discount_percent}}% off your next order.',
        );
    }
}
