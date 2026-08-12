<?php

namespace Tests\Feature\Nexus\Catalog;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\AddServiceAction;
use App\Domains\Nexus\Catalog\Application\Actions\RejectProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\RejectServiceAction;
use App\Domains\Nexus\Catalog\Application\Actions\VerifyProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\VerifyServiceAction;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\ListingVerificationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeBusinessId(): int
    {
        return app(RegisterBusinessAction::class)
            ->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology)
            ->id;
    }

    public function test_newProduct_startsPending(): void
    {
        $businessId = $this->makeBusinessId();

        $product = app(AddProductAction::class)->execute($businessId, 'محصول', 'Test Product', 50000, 'IRT', 10);

        $this->assertSame('pending', $product->verificationStatus);
    }

    public function test_verifyProduct_marksVerified(): void
    {
        $businessId = $this->makeBusinessId();
        $product = app(AddProductAction::class)->execute($businessId, 'محصول', 'Test Product', 50000, 'IRT', 10);

        $result = app(VerifyProductAction::class)->execute($product->id);

        $this->assertSame('verified', $result->verificationStatus);
        $stored = app(ProductRepositoryInterface::class)->findById($product->id);
        $this->assertTrue($stored->isVerified());
    }

    public function test_rejectProduct_marksRejected(): void
    {
        $businessId = $this->makeBusinessId();
        $product = app(AddProductAction::class)->execute($businessId, 'محصول', 'Test Product', 50000, 'IRT', 10);

        $result = app(RejectProductAction::class)->execute($product->id);

        $this->assertSame('rejected', $result->verificationStatus);
    }

    public function test_verifyService_marksVerified(): void
    {
        $businessId = $this->makeBusinessId();
        $service = app(AddServiceAction::class)->execute($businessId, 'خدمت', 'Test Service', 200000, 'IRT', 60);

        $result = app(VerifyServiceAction::class)->execute($service->id);

        $this->assertSame('verified', $result->verificationStatus);
    }

    public function test_rejectService_marksRejected(): void
    {
        $businessId = $this->makeBusinessId();
        $service = app(AddServiceAction::class)->execute($businessId, 'خدمت', 'Test Service', 200000, 'IRT', 60);

        $result = app(RejectServiceAction::class)->execute($service->id);

        $this->assertSame('rejected', $result->verificationStatus);
    }

    public function test_productRepository_findByVerificationStatus_filtersCorrectly(): void
    {
        $businessId = $this->makeBusinessId();
        $pending = app(AddProductAction::class)->execute($businessId, 'محصول ۱', 'Product 1', 1000, 'IRT');
        $toVerify = app(AddProductAction::class)->execute($businessId, 'محصول ۲', 'Product 2', 1000, 'IRT');
        app(VerifyProductAction::class)->execute($toVerify->id);

        $pendingProducts = app(ProductRepositoryInterface::class)->findByVerificationStatus(ListingVerificationStatus::Pending);
        $verifiedProducts = app(ProductRepositoryInterface::class)->findByVerificationStatus(ListingVerificationStatus::Verified);

        $this->assertCount(1, $pendingProducts);
        $this->assertSame($pending->id, $pendingProducts[0]->id());
        $this->assertCount(1, $verifiedProducts);
        $this->assertSame($toVerify->id, $verifiedProducts[0]->id());
    }

    public function test_serviceRepository_findByVerificationStatus_filtersCorrectly(): void
    {
        $businessId = $this->makeBusinessId();
        app(AddServiceAction::class)->execute($businessId, 'خدمت', 'Test Service', 200000, 'IRT', 60);

        $pendingServices = app(ServiceRepositoryInterface::class)->findByVerificationStatus(ListingVerificationStatus::Pending);

        $this->assertCount(1, $pendingServices);
    }
}
