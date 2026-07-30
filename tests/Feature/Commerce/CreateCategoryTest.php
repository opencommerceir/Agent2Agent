<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCategoryAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidData_persistsCategoryWithGeneratedSlug(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $result = app(CreateCategoryAction::class)->execute($tenant->id, 'Home & Garden', 'Everything for the home.');

        $this->assertNotNull($result->id);
        $this->assertSame('home-garden', $result->slug);
        $this->assertDatabaseHas('categories', [
            'tenant_id' => $tenant->id,
            'name' => 'Home & Garden',
            'slug' => 'home-garden',
        ]);
    }

    public function test_execute_withDuplicateSlugInSameTenant_throwsInvalidArgumentException(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(CreateCategoryAction::class)->execute($tenant->id, 'Electronics');

        $this->expectException(InvalidArgumentException::class);

        app(CreateCategoryAction::class)->execute($tenant->id, 'Electronics');
    }

    public function test_execute_withSameSlugInDifferentTenants_doesNotConflict(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Globex Inc', 'globex-'.uniqid());

        app(CreateCategoryAction::class)->execute($tenantA->id, 'Electronics');
        $result = app(CreateCategoryAction::class)->execute($tenantB->id, 'Electronics');

        $this->assertNotNull($result->id);
        $this->assertDatabaseCount('categories', 2);
    }
}
