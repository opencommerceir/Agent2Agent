<?php

namespace Tests\Feature\Nexus\Audit;

use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Audit\Application\Actions\VerifyAuditChainIntegrityAction;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;
use App\Domains\Nexus\Audit\Infrastructure\Models\AuditLogEntry as AuditLogEntryModel;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Database\Seeders\NexusCreditCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7/M9 — real MCP capability calls over POST /mcp/v1/execute
 * producing real hash-chained rows in nexus_audit_log_entries, the same
 * "prove it through the real Gateway, not just at the Action level"
 * standard NegotiationCapabilityTest already set for Phase 2/M4.
 */
class AuditTrailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusCreditCapabilitiesSeeder::class);
    }

    public function test_realMcpCall_producesAChainedAuditEntry(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $token = $this->tokenFor($business->id, ['nexus.credit.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.credit.balance',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);

        $repository = app(AuditLogEntryRepositoryInterface::class);
        $this->assertSame(1, $repository->count());

        $entry = $repository->latest(1)[0];
        $this->assertSame(1, $entry->sequence());
        $this->assertSame('nexus.credit.balance', $entry->capabilityName());
        $this->assertSame($business->id, $entry->businessId());
        $this->assertSame('success', $entry->status()->value);
        $this->assertSame(64, strlen($entry->prevHash()));
        $this->assertSame(str_repeat('0', 64), $entry->prevHash());
        $this->assertSame(64, strlen($entry->entryHash()));
    }

    public function test_multipleRealMcpCalls_chainSequentially(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $token = $this->tokenFor($business->id, ['nexus.credit.read']);

        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);
        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);
        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);

        $repository = app(AuditLogEntryRepositoryInterface::class);
        $entries = $repository->allOrderedBySequence();

        $this->assertCount(3, $entries);
        $this->assertSame([1, 2, 3], array_map(fn ($e) => $e->sequence(), $entries));
        $this->assertSame($entries[0]->entryHash(), $entries[1]->prevHash());
        $this->assertSame($entries[1]->entryHash(), $entries[2]->prevHash());
    }

    public function test_suspendedBusinessAgent_deniedCall_isLoggedAsDeniedWithBusinessIdKnown(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $token = $this->tokenFor($business->id, ['nexus.credit.read']);
        app(SuspendBusinessAction::class)->execute($business->id, 'fraud investigation');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.credit.balance',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);

        $repository = app(AuditLogEntryRepositoryInterface::class);
        $entry = $repository->latest(1)[0];
        $this->assertSame('denied', $entry->status()->value);
        // The whole point of resolving the Business independently of the
        // suspension check (RecordAuditEntryAction's own docblock) — a
        // compliance trail that can't say *who* was denied is useless.
        $this->assertSame($business->id, $entry->businessId());
    }

    public function test_verifyChainIntegrity_onUntamperedChain_reportsIntact(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $token = $this->tokenFor($business->id, ['nexus.credit.read']);

        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);
        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);

        $result = app(VerifyAuditChainIntegrityAction::class)->execute();

        $this->assertTrue($result['intact']);
        $this->assertSame(2, $result['checkedCount']);
        $this->assertNull($result['brokenAtSequence']);
    }

    public function test_verifyChainIntegrity_onTamperedRow_detectsTheBreak(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $token = $this->tokenFor($business->id, ['nexus.credit.read']);

        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);
        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);
        $this->postJson('/mcp/v1/execute', ['capability' => 'nexus.credit.balance', 'input' => []], ['Authorization' => "Bearer {$token}"]);

        // Simulate someone editing a historical row directly in the
        // database (bypassing the Domain layer entirely) — exactly the
        // tampering scenario a hash-chained ledger exists to catch.
        AuditLogEntryModel::query()->where('sequence', 2)->update(['status' => 'denied']);

        $result = app(VerifyAuditChainIntegrityAction::class)->execute();

        $this->assertFalse($result['intact']);
        $this->assertSame(2, $result['brokenAtSequence']);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
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
