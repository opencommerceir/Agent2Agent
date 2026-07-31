<?php

namespace App\Console\Commands;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Modules\Commerce\Application\Actions\MarkCartsAbandonedAction;
use Illuminate\Console\Command;

/**
 * Scheduled hourly via routes/console.php — the missing piece Workflows'
 * CartAbandonedListener has been blocked on since Phase 3.3 (HANDOFF
 * §8.23/§8.27: "no scheduling mechanism exists anywhere in this codebase
 * yet"). Iterates every Tenant and marks Carts idle past 24h as
 * Abandoned, dispatching CartWasAbandoned per cart.
 */
class MarkAbandonedCartsCommand extends Command
{
    protected $signature = 'commerce:check-abandoned-carts';

    protected $description = 'Mark Carts as abandoned if inactive for 24 hours';

    public function handle(TenantRepositoryInterface $tenants, MarkCartsAbandonedAction $markCartsAbandoned): int
    {
        $this->info('Checking for abandoned carts...');

        $totalAbandoned = 0;

        foreach ($tenants->all() as $tenant) {
            $totalAbandoned += $markCartsAbandoned->execute($tenant->id(), hoursIdle: 24);
        }

        $this->info("Marked {$totalAbandoned} cart(s) as abandoned.");

        return self::SUCCESS;
    }
}
