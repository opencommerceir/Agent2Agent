<?php

namespace App\Console\Commands;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Modules\Loyalty\Application\Actions\BulkExpirePointsAction;
use Illuminate\Console\Command;

/**
 * Scheduled daily via routes/console.php — the piece HANDOFF §8.23/§8.27
 * flagged as missing ("no scheduling mechanism exists anywhere in this
 * codebase yet"). Iterates every Tenant (TenantRepositoryInterface::all(),
 * added for this) and runs BulkExpirePointsAction once per tenant.
 */
class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = "Expire due loyalty points across every tenant's LoyaltyAccounts";

    public function handle(TenantRepositoryInterface $tenants, BulkExpirePointsAction $bulkExpirePoints): int
    {
        $this->info('Starting loyalty points expiration...');

        $accountsAffected = 0;
        $transactionsCreated = 0;

        foreach ($tenants->all() as $tenant) {
            $result = $bulkExpirePoints->execute($tenant->id());
            $accountsAffected += $result['accounts_affected'];
            $transactionsCreated += $result['transactions_created'];
        }

        $this->info("Done: {$accountsAffected} account(s) affected, {$transactionsCreated} expire transaction(s) created.");

        return self::SUCCESS;
    }
}
