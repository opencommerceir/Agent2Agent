<?php

namespace Database\Seeders;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Domain\Repositories\UserRepositoryInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. A default Dashboard admin (Phase 4
     * Stage 5) — guarded by an existence check so re-running `db:seed`
     * against a database that already has one doesn't throw the
     * CreateUserAction's own duplicate-email error.
     */
    public function run(): void
    {
        if (! app(UserRepositoryInterface::class)->emailExists('admin@opencommerce.test')) {
            app(CreateUserAction::class)->execute('Admin', 'admin@opencommerce.test', 'password', 'admin');
        }

        $this->call(DemoCapabilitiesSeeder::class);
        $this->call(CommerceCapabilitiesSeeder::class);
        $this->call(CRMCapabilitiesSeeder::class);
        $this->call(FinanceCapabilitiesSeeder::class);
        $this->call(WorkflowsCapabilitiesSeeder::class);
        $this->call(LoyaltyCapabilitiesSeeder::class);
        $this->call(ReportingCapabilitiesSeeder::class);
        $this->call(ShippingCapabilitiesSeeder::class);
        $this->call(NotificationsCapabilitiesSeeder::class);
        $this->call(AnalyticsCapabilitiesSeeder::class);
        $this->call(AgentOrchestratorCapabilitiesSeeder::class);
        $this->call(NexusMarketplaceCapabilitiesSeeder::class);
        $this->call(NexusNegotiationCapabilitiesSeeder::class);
        $this->call(NexusCreditCapabilitiesSeeder::class);
    }
}
