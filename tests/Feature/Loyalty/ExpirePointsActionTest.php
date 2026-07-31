<?php

namespace Tests\Feature\Loyalty;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Loyalty\Application\Actions\CreateLoyaltyAccountAction;
use App\Modules\Loyalty\Application\Actions\ExpirePointsAction;
use App\Modules\Loyalty\Domain\Entities\PointTransaction;
use App\Modules\Loyalty\Domain\Exceptions\LoyaltyAccountNotFoundException;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ExpirePointsAction is not wired to MCP (LoyaltyCapabilities' own
 * docblock) — exercised directly, same shape
 * tests/Feature/Finance/UpdateTaxRateActionTest.php has for that
 * module's own un-wired Action.
 *
 * EarnPointsAction always sets a future expires_at (365 days out), so
 * these tests bypass it and write PointTransaction rows directly through
 * the Repository to simulate points that were earned long enough ago to
 * already be due.
 */
class ExpirePointsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_expiresPointsPastTheirExpiryDate(): void
    {
        [$tenantId, $accountId] = $this->openAccountWithBalance(100);

        $this->earnWithExpiry($tenantId, $accountId, 100, new DateTimeImmutable('yesterday'));

        $created = app(ExpirePointsAction::class)->execute($tenantId, $accountId);

        $this->assertCount(1, $created);
        $this->assertSame(-100, $created[0]['points']);
        $this->assertSame('expire', $created[0]['transactionType']);

        $account = app(LoyaltyAccountRepositoryInterface::class)->findById($accountId, $tenantId);
        $this->assertSame(0, $account->currentBalance()->value());
    }

    public function test_execute_doesNotExpirePointsNotYetDue(): void
    {
        [$tenantId, $accountId] = $this->openAccountWithBalance(100);

        $this->earnWithExpiry($tenantId, $accountId, 100, new DateTimeImmutable('+1 year'));

        $created = app(ExpirePointsAction::class)->execute($tenantId, $accountId);

        $this->assertSame([], $created);

        $account = app(LoyaltyAccountRepositoryInterface::class)->findById($accountId, $tenantId);
        $this->assertSame(100, $account->currentBalance()->value());
    }

    public function test_execute_clampsExpiryToWhateverBalanceRemainsAfterARedemption(): void
    {
        [$tenantId, $accountId] = $this->openAccountWithBalance(100);
        $this->earnWithExpiry($tenantId, $accountId, 100, new DateTimeImmutable('yesterday'));

        // Simulate 60 already redeemed — only 40 genuinely remains.
        $accounts = app(LoyaltyAccountRepositoryInterface::class);
        $account = $accounts->findById($accountId, $tenantId);
        $account->redeem(new Points(60));
        $accounts->save($account);

        $created = app(ExpirePointsAction::class)->execute($tenantId, $accountId);

        $this->assertCount(1, $created);
        $this->assertSame(-40, $created[0]['points']);

        $account = $accounts->findById($accountId, $tenantId);
        $this->assertSame(0, $account->currentBalance()->value());
    }

    public function test_execute_forNonexistentAccount_throwsLoyaltyAccountNotFoundException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $this->expectException(LoyaltyAccountNotFoundException::class);

        app(ExpirePointsAction::class)->execute($tenant->id, 999999);
    }

    public function test_execute_forAccountInAnotherTenant_throwsLoyaltyAccountNotFoundException(): void
    {
        [, $accountId] = $this->openAccountWithBalance(100);
        $otherTenant = app(CreateTenantAction::class)->execute('Other Inc', 'other-'.uniqid());

        $this->expectException(LoyaltyAccountNotFoundException::class);

        app(ExpirePointsAction::class)->execute($otherTenant->id, $accountId);
    }

    /**
     * @return array{0: int, 1: int} [tenantId, loyaltyAccountId]
     */
    private function openAccountWithBalance(int $points): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $customer = app(CreateCustomerAction::class)->execute($tenant->id, 'Test', 'Customer', 'test-'.uniqid().'@example.com');
        $account = app(CreateLoyaltyAccountAction::class)->execute($tenant->id, $customer->id);

        return [$tenant->id, $account->id];
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
