<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\AddServiceAction;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-only Verification queue (Phase 6/M5) — core `auth`/`admin` guard,
 * same createAdmin() pattern NexusEscrowControllerTest establishes.
 */
class NexusVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    private function makeBusinessId(): int
    {
        return app(RegisterBusinessAction::class)
            ->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology)
            ->id;
    }

    public function test_index_listsPendingBusinessesProductsAndServices(): void
    {
        $admin = $this->createAdmin();
        $businessId = $this->makeBusinessId();
        app(AddProductAction::class)->execute($businessId, 'محصول', 'Pending Product', 1000, 'IRT');
        app(AddServiceAction::class)->execute($businessId, 'خدمت', 'Pending Service', 2000, 'IRT');

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.verification.index'));

        $response->assertStatus(200);
        $response->assertSee('Pending Product');
        $response->assertSee('Pending Service');
        $response->assertSee('Test Company');
    }

    public function test_verifyBusiness_marksBusinessVerified(): void
    {
        $admin = $this->createAdmin();
        $businessId = $this->makeBusinessId();

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.verification.businesses.verify', $businessId));

        $response->assertRedirect(route('dashboard.nexus.verification.index'));
        $business = app(BusinessRepositoryInterface::class)->findById($businessId);
        $this->assertTrue($business->isVerified());
    }

    public function test_verifyProduct_marksProductVerified(): void
    {
        $admin = $this->createAdmin();
        $businessId = $this->makeBusinessId();
        $product = app(AddProductAction::class)->execute($businessId, 'محصول', 'Test Product', 1000, 'IRT');

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.verification.products.verify', $product->id));

        $response->assertRedirect(route('dashboard.nexus.verification.index'));
        $stored = app(ProductRepositoryInterface::class)->findById($product->id);
        $this->assertTrue($stored->isVerified());
    }

    public function test_rejectService_marksServiceRejected(): void
    {
        $admin = $this->createAdmin();
        $businessId = $this->makeBusinessId();
        $service = app(AddServiceAction::class)->execute($businessId, 'خدمت', 'Test Service', 2000, 'IRT');

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.verification.services.reject', $service->id));

        $response->assertRedirect(route('dashboard.nexus.verification.index'));
        $stored = app(ServiceRepositoryInterface::class)->findById($service->id);
        $this->assertSame('rejected', $stored->verificationStatus()->value);
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.verification.index'))->assertRedirect('/login');
    }
}
