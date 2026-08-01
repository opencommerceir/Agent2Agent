<?php

namespace Tests\Feature\Core;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\SetTenantDefaultLanguageAction;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * SetTenantDefaultLanguageAction isn't wired to any MCP capability this
 * stage (no capability was requested for it — HANDOFF §6/§8.2's usual
 * "built, tested, not yet exposed to Agents" gap), so it's exercised
 * directly here, the same way Finance's UpdateTaxRateActionTest does for
 * its own un-wired Action.
 */
class TenantDefaultLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_persistsTheNewDefaultLanguage(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $this->assertSame('en', $tenant->defaultLanguage);

        $updated = app(SetTenantDefaultLanguageAction::class)->execute($tenant->id, 'fa');

        $this->assertSame('fa', $updated->defaultLanguage);

        $reloaded = app(TenantRepositoryInterface::class)->findById($tenant->id);
        $this->assertSame('fa', $reloaded->defaultLanguage()->value);
    }

    public function test_execute_forNonexistentTenant_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SetTenantDefaultLanguageAction::class)->execute(999999, 'fa');
    }
}
