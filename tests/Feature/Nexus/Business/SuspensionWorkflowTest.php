<?php

namespace Tests\Feature\Nexus\Business;

use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\ReactivateBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\ResolveSuspensionAppealAction;
use App\Domains\Nexus\Business\Application\Actions\SubmitSuspensionAppealAction;
use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionRecordRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionTrigger;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Database\Seeders\NexusCreditCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SuspensionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusCreditCapabilitiesSeeder::class);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    public function test_suspend_recordsSuspensionAndFlipsStatus(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');

        $result = app(SuspendBusinessAction::class)->execute($business->id, 'test suspension');

        $this->assertSame('suspended', $result->status);
        $records = app(SuspensionRecordRepositoryInterface::class)->findByBusinessId($business->id);
        $this->assertCount(1, $records);
        $this->assertSame('suspended', $records[0]->action()->value);
        $this->assertSame('admin', $records[0]->triggeredBy()->value);
    }

    public function test_reactivate_recordsReactivationAndFlipsStatus(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'test suspension');

        $result = app(ReactivateBusinessAction::class)->execute($business->id, 'test reactivation');

        $this->assertSame('active', $result->status);
        $records = app(SuspensionRecordRepositoryInterface::class)->findByBusinessId($business->id);
        $this->assertCount(2, $records);
    }

    public function test_suspendedBusinessAgent_isBlockedFromMcpCapabilities(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'fraud');
        $token = $this->tokenFor($business->id, ['nexus.credit.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.credit.balance',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    public function test_activeBusinessAgent_isNotBlocked(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $token = $this->tokenFor($business->id, ['nexus.credit.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.credit.balance',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
    }

    public function test_submitSuspensionAppeal_onlyAllowedWhileSuspended(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');

        $this->expectException(InvalidArgumentException::class);

        app(SubmitSuspensionAppealAction::class)->execute($business->id, 'please review');
    }

    public function test_resolveSuspensionAppeal_approved_reactivatesBusiness(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'fraud');
        $appeal = app(SubmitSuspensionAppealAction::class)->execute($business->id, 'it was a mistake');

        app(ResolveSuspensionAppealAction::class)->execute($appeal->id, true);

        $updated = app(BusinessRepositoryInterface::class)->findById($business->id);
        $this->assertTrue($updated->isActive());
    }

    public function test_resolveSuspensionAppeal_denied_keepsBusinessSuspended(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        app(SuspendBusinessAction::class)->execute($business->id, 'fraud');
        $appeal = app(SubmitSuspensionAppealAction::class)->execute($business->id, 'it was a mistake');

        $result = app(ResolveSuspensionAppealAction::class)->execute($appeal->id, false);

        $this->assertSame('denied', $result->status);
        $updated = app(BusinessRepositoryInterface::class)->findById($business->id);
        $this->assertFalse($updated->isActive());
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function tokenFor(int $businessId, array $permissionKeys): string
    {
        $business = app(BusinessRepositoryInterface::class)->findById($businessId);
        $nexusAgent = app(AgentRepositoryInterface::class)->findByBusinessId($businessId);

        $role = app(CreateRoleAction::class)->execute($business->tenantId(), 'Caller', 'caller-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $nexusAgent->coreAgentId(), $role->id);

        return app(GenerateAgentTokenAction::class)->execute($nexusAgent->coreAgentId())->plainToken;
    }
}
