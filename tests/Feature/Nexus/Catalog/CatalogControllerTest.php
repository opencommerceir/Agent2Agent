<?php

namespace Tests\Feature\Nexus\Catalog;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\AddServiceAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.catalog.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_index_listsOwnProductsAndServices(): void
    {
        $owner = $this->ownerWithBusiness();
        app(AddProductAction::class)->execute($owner->business_id, 'محصول آزمایشی', 'Test Product', 50000, 'IRT', 10);
        app(AddServiceAction::class)->execute($owner->business_id, 'خدمت آزمایشی', 'Test Service', 200000, 'IRT', 60);

        $response = $this->actingAs($owner, 'business')->get(route('nexus.catalog.index'));

        $response->assertOk();
        $response->assertSee('Test Product');
        $response->assertSee('Test Service');
    }

    public function test_index_neverShowsAnotherBusinessCatalog(): void
    {
        $owner = $this->ownerWithBusiness();
        $otherBusinessId = app(RegisterBusinessAction::class)->execute('شرکت دیگر', 'Other Company', BusinessType::Company, Industry::Retail)->id;
        app(AddProductAction::class)->execute($otherBusinessId, 'محصول دیگر', 'Other Product', 1000, 'IRT');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.catalog.index'));

        $response->assertOk();
        $response->assertDontSee('Other Product');
    }

    public function test_storeProduct_withValidData_createsProductAndRedirects(): void
    {
        $owner = $this->ownerWithBusiness();

        $response = $this->actingAs($owner, 'business')->post(route('nexus.catalog.products.store'), [
            'name_fa' => 'محصول جدید',
            'name_en' => 'New Product',
            'price_amount' => 120000,
            'stock_quantity' => 3,
        ]);

        $response->assertRedirect(route('nexus.catalog.index'));
        $this->assertDatabaseHas('nexus_products', [
            'business_id' => $owner->business_id,
            'name_en' => 'New Product',
            'price_amount' => 120000,
            'stock_quantity' => 3,
        ]);
    }

    public function test_storeService_withValidData_createsServiceAndRedirects(): void
    {
        $owner = $this->ownerWithBusiness();

        $response = $this->actingAs($owner, 'business')->post(route('nexus.catalog.services.store'), [
            'name_fa' => 'خدمت جدید',
            'name_en' => 'New Service',
            'price_amount' => 300000,
            'duration_minutes' => 45,
        ]);

        $response->assertRedirect(route('nexus.catalog.index'));
        $this->assertDatabaseHas('nexus_services', [
            'business_id' => $owner->business_id,
            'name_en' => 'New Service',
            'duration_minutes' => 45,
        ]);
    }

    public function test_editProduct_forOwnProduct_showsForm(): void
    {
        $owner = $this->ownerWithBusiness();
        $product = app(AddProductAction::class)->execute($owner->business_id, 'محصول آزمایشی', 'Test Product', 50000, 'IRT', 10);

        $response = $this->actingAs($owner, 'business')->get(route('nexus.catalog.products.edit', $product->id));

        $response->assertOk();
        $response->assertSee('Test Product');
    }

    public function test_editProduct_forAnotherBusinessProduct_isForbidden(): void
    {
        $owner = $this->ownerWithBusiness();
        $otherBusinessId = app(RegisterBusinessAction::class)->execute('شرکت دیگر', 'Other Company', BusinessType::Company, Industry::Retail)->id;
        $product = app(AddProductAction::class)->execute($otherBusinessId, 'محصول دیگر', 'Other Product', 1000, 'IRT');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.catalog.products.edit', $product->id));

        $response->assertForbidden();
    }

    public function test_updateProduct_withValidData_updatesAndRedirects(): void
    {
        $owner = $this->ownerWithBusiness();
        $product = app(AddProductAction::class)->execute($owner->business_id, 'محصول آزمایشی', 'Test Product', 50000, 'IRT', 10);

        $response = $this->actingAs($owner, 'business')->put(route('nexus.catalog.products.update', $product->id), [
            'name_fa' => 'محصول جدید',
            'name_en' => 'Updated Product',
            'price_amount' => 75000,
            'stock_quantity' => 5,
        ]);

        $response->assertRedirect(route('nexus.catalog.index'));
        $this->assertDatabaseHas('nexus_products', ['id' => $product->id, 'name_en' => 'Updated Product', 'price_amount' => 75000]);
    }

    public function test_updateProduct_forAnotherBusinessProduct_isForbidden(): void
    {
        $owner = $this->ownerWithBusiness();
        $otherBusinessId = app(RegisterBusinessAction::class)->execute('شرکت دیگر', 'Other Company', BusinessType::Company, Industry::Retail)->id;
        $product = app(AddProductAction::class)->execute($otherBusinessId, 'محصول دیگر', 'Other Product', 1000, 'IRT');

        $response = $this->actingAs($owner, 'business')->put(route('nexus.catalog.products.update', $product->id), [
            'name_fa' => 'x',
            'name_en' => 'y',
            'price_amount' => 1,
            'stock_quantity' => 0,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('nexus_products', ['id' => $product->id, 'name_en' => 'Other Product']);
    }

    public function test_updateService_withValidData_updatesAndRedirects(): void
    {
        $owner = $this->ownerWithBusiness();
        $service = app(AddServiceAction::class)->execute($owner->business_id, 'خدمت آزمایشی', 'Test Service', 200000, 'IRT', 60);

        $response = $this->actingAs($owner, 'business')->put(route('nexus.catalog.services.update', $service->id), [
            'name_fa' => 'خدمت جدید',
            'name_en' => 'Updated Service',
            'price_amount' => 250000,
            'duration_minutes' => 90,
        ]);

        $response->assertRedirect(route('nexus.catalog.index'));
        $this->assertDatabaseHas('nexus_services', ['id' => $service->id, 'name_en' => 'Updated Service', 'duration_minutes' => 90]);
    }

    private function ownerWithBusiness(): BusinessOwner
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'role' => TeamMemberRole::Owner->value,
        ]);
    }
}
