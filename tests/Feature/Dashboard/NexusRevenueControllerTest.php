<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\ConfirmCreditPurchaseAction;
use App\Domains\Nexus\Credit\Application\Actions\PurchaseCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Modules\Commerce\Application\Services\MockRedirectPaymentGateway;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusRevenueControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_showsCreditPackageRevenue(): void
    {
        app(PaymentGatewayRegistry::class)->register('zibal', new MockRedirectPaymentGateway());
        $business = app(RegisterBusinessAction::class)->execute('نام Buyer Co', 'Buyer Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $purchase = app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter);
        app(ConfirmCreditPurchaseAction::class)->execute($purchase['tracking_reference']);

        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->get(route('dashboard.nexus.revenue.index'));

        $response->assertStatus(200);
        $response->assertSee('500,000');
        $response->assertSee('Buyer Co');
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.revenue.index'))->assertRedirect('/login');
    }
}
