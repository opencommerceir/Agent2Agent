<?php

namespace Tests\Feature\Loyalty;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Loyalty\Application\Actions\CreateLoyaltyAccountAction;
use App\Modules\Loyalty\Domain\Entities\PointTransaction;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The scheduled `loyalty:expire-points` command (HANDOFF §8.23/§8.27),
 * run end to end across two Tenants via TenantRepositoryInterface::all()
 * and BulkExpirePointsAction's allForTenant() fan-out — proves the
 * cross-tenant iteration, not just ExpirePointsAction in isolation
 * (already covered by ExpirePointsActionTest). Same "write PointTransaction
 * rows directly with a past expires_at" simulation ExpirePointsActionTest
 * already uses, since EarnPointsAction always sets a future one.
 */
class ExpireLoyaltyPointsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_expiresDuePointsAcrossTenantsAndLeavesNotYetDueAlone(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $customerA = app(CreateCustomerAction::class)->execute($tenantA->id, 'A', 'One', 'a-'.uniqid().'@example.com');
        $accountA = app(CreateLoyaltyAccountAction::class)->execute($tenantA->id, $customerA->id);
        $this->earnWithExpiry($tenantA->id, $accountA->id, 100, new DateTimeImmutable('yesterday'));

        $tenantB = app(CreateTenantAction::class)->execute('Other Inc', 'other-'.uniqid());
        $customerB = app(CreateCustomerAction::class)->execute($tenantB->id, 'B', 'Two', 'b-'.uniqid().'@example.com');
        $accountB = app(CreateLoyaltyAccountAction::class)->execute($tenantB->id, $customerB->id);
        $this->earnWithExpiry($tenantB->id, $accountB->id, 50, new DateTimeImmutable('+1 year'));

        $this->artisan('loyalty:expire-points')->assertExitCode(0);

        $accounts = app(LoyaltyAccountRepositoryInterface::class);
        $this->assertSame(0, $accounts->findById($accountA->id, $tenantA->id)->currentBalance()->value());
        $this->assertSame(50, $accounts->findById($accountB->id, $tenantB->id)->currentBalance()->value());
    }

    private function earnWithExpiry(int $tenantId, int $accountId, int $points, DateTimeImmutable $expiresAt): void
    {
        $accounts = app(LoyaltyAccountRepositoryInterface::class);
        $account = $accounts->findById($accountId, $tenantId);
        $account->earn(new Points($points));
        $accounts->save($account);

        app(PointTransactionRepositoryInterface::class)->save(
            PointTransaction::record($tenantId, $accountId, $points, TransactionType::Earn, 'Test earn', null, $expiresAt),
        );
    }
}
