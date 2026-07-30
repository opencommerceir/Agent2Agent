<?php

namespace Tests\Feature\Core;

use App\Core\Application\Actions\CreateTenantAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * There is no HTTP endpoint for Tenant management yet — Core exposes it
 * only as an Action that other modules/tooling call directly (no
 * `app/Core/Interfaces/HTTP` route currently targets it). This test still
 * belongs in Feature rather than Unit because it exercises the full
 * container + database wiring: CreateTenantAction -> EloquentTenantRepository
 * -> the real `tenants` table, via RefreshDatabase.
 */
class CreateTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_persistsTenantAndReturnsPendingStatus(): void
    {
        $result = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-inc');

        $this->assertNotNull($result->id);
        $this->assertSame('Acme Inc', $result->name);
        $this->assertSame('acme-inc', $result->slug);
        $this->assertSame('pending', $result->status);
        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
            'status' => 'pending',
        ]);
    }

    public function test_execute_withDuplicateSlug_throwsInvalidArgumentExceptionAndDoesNotDuplicateRow(): void
    {
        app(CreateTenantAction::class)->execute('Acme Inc', 'acme-inc');

        $this->expectException(InvalidArgumentException::class);

        try {
            app(CreateTenantAction::class)->execute('Acme Inc Two', 'acme-inc');
        } finally {
            $this->assertDatabaseCount('tenants', 1);
        }
    }
}
