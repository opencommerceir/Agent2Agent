<?php

namespace App\Console\Commands;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipes and rebuilds the `/showcase` chat UI's demo fixture
 * (DemoShowcaseSeeder) — run between demo sessions so a Tenant that
 * accumulated real chat-driven Orders/Coupons/Executions during one
 * showcase run starts clean for the next.
 *
 * No `TenantRepositoryInterface::delete()` (or any cascading-delete
 * Action) exists anywhere in this codebase — deliberately never
 * requested/built for any entity (HANDOFF §8's own running list of
 * documented gaps). Rather than inventing a new Domain-layer deletion
 * capability for one demo-only utility, this Command reaches the
 * `tenants` table directly via the query builder and relies on
 * referential integrity the schema itself already owns: every
 * tenant-scoped migration in this codebase declares its own `tenant_id`
 * foreign key with `->cascadeOnDelete()` (products, orders, agents,
 * agent_tokens, roles, warehouses, tickets, loyalty_accounts, ... —
 * confirmed against every migration this seeder's own data touches).
 * Deleting the one `tenants` row therefore cascades through the entire
 * tree this seeder built, in one statement, with no need to enumerate
 * or order dozens of child tables by hand. The one deliberate exception
 * is the global `permissions` table (no `tenant_id` at all — shared
 * vocabulary across every Tenant, see that migration's own docblock),
 * which this reset correctly never touches.
 */
class ResetDemoShowcaseCommand extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Wipe and reseed the /showcase demo tenant (DemoShowcaseSeeder)';

    public function handle(TenantRepositoryInterface $tenants): int
    {
        $existing = $tenants->findBySlug(DemoShowcaseSeeder::TENANT_SLUG);

        if ($existing !== null) {
            $this->info("Removing existing demo tenant [#{$existing->id()}] and every record it cascades to...");
            DB::table('tenants')->where('id', $existing->id())->delete();
        } else {
            $this->info('No existing demo tenant found — seeding fresh.');
        }

        $this->call('db:seed', ['--class' => DemoShowcaseSeeder::class, '--force' => true]);

        $this->info('Demo showcase tenant is ready. Visit /showcase to try it.');

        return self::SUCCESS;
    }
}
