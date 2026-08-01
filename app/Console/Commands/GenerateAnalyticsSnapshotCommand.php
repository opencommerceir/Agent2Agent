<?php

namespace App\Console\Commands;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Modules\Analytics\Application\Actions\GenerateSnapshotAction;
use Illuminate\Console\Command;

/**
 * Scheduled daily via routes/console.php — the same cross-tenant
 * iteration shape `ExpireLoyaltyPointsCommand`/`MarkAbandonedCartsCommand`
 * already established (`TenantRepositoryInterface::all()`, one
 * `GenerateSnapshotAction::execute()` call per Tenant).
 */
class GenerateAnalyticsSnapshotCommand extends Command
{
    protected $signature = 'analytics:generate-snapshot';

    protected $description = "Generate today's AnalyticsSnapshot for every tenant";

    public function handle(TenantRepositoryInterface $tenants, GenerateSnapshotAction $generateSnapshot): int
    {
        $this->info('Generating analytics snapshots...');

        $count = 0;

        foreach ($tenants->all() as $tenant) {
            $generateSnapshot->execute($tenant->id());
            $count++;
        }

        $this->info("Done: {$count} snapshot(s) generated.");

        return self::SUCCESS;
    }
}
