<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Analytics\Application\Actions\GetRevenueDashboardAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Credit\Application\Actions\ConfirmCreditPurchaseAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Application\Actions\PurchaseCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Modules\Commerce\Application\Services\MockRedirectPaymentGateway;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetRevenueDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PaymentGatewayRegistry::class)->register('zibal', new MockRedirectPaymentGateway());
    }

    private function verifiedBusiness(string $nameEn, string $industry = 'technology'): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::from($industry));
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    public function test_execute_countsCompletedCreditPurchaseAsRevenue(): void
    {
        $business = $this->verifiedBusiness('Buyer Co');
        $purchase = app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter);
        app(ConfirmCreditPurchaseAction::class)->execute($purchase['tracking_reference']);

        $result = app(GetRevenueDashboardAction::class)->execute();

        $this->assertSame(500_000, $result['creditPackageRevenue']['amount']);
        $this->assertSame(1, $result['creditPackageRevenue']['count']);
        $this->assertSame(500_000, $result['grossRevenue']);
        $this->assertSame(500_000, $result['netRevenue']);
    }

    public function test_execute_countsReleasedEscrowFeeAsRevenue_normalizedToWholeToman(): void
    {
        config(['nexus.platform.margin.transaction_fee_percent' => 0.5]);
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $seller->id);

        $result = app(GetRevenueDashboardAction::class)->execute();

        // Escrow gross is 1_000_000 minor units (Negotiation's own Money
        // scale) => 10,000 real Toman; 0.5% fee => 50 Toman, not 5000.
        $this->assertSame(50, $result['escrowFeeRevenue']['amount']);
        $this->assertSame(1, $result['escrowFeeRevenue']['count']);
        $this->assertSame(50, $result['grossRevenue']);
    }

    public function test_execute_heldEscrow_countsAsPendingNotRevenue(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $result = app(GetRevenueDashboardAction::class)->execute();

        $this->assertSame(0, $result['escrowFeeRevenue']['amount']);
        $this->assertSame(1, $result['escrowPending']['count']);
        $this->assertSame(10_000, $result['escrowPending']['grossAmount']);
    }

    public function test_execute_reportsCreditsDeducted(): void
    {
        $business = $this->verifiedBusiness('Caller Co');

        $result = app(GetRevenueDashboardAction::class)->execute();

        // verifiedBusiness() alone doesn't deduct anything — a sanity
        // baseline that the key exists and starts at 0.
        $this->assertSame(0, $result['creditsDeducted']);
    }

    public function test_execute_perBusinessAndPerIndustry_breakDownCorrectly(): void
    {
        $business = $this->verifiedBusiness('Tech Co', 'technology');
        $purchase = app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter);
        app(ConfirmCreditPurchaseAction::class)->execute($purchase['tracking_reference']);

        $result = app(GetRevenueDashboardAction::class)->execute();

        $businessRow = collect($result['perBusiness'])->firstWhere('businessId', $business->id);
        $this->assertNotNull($businessRow);
        $this->assertSame(500_000, $businessRow['creditPackageRevenue']);

        $industryRow = collect($result['perIndustry'])->firstWhere('industry', 'technology');
        $this->assertNotNull($industryRow);
        $this->assertSame(500_000, $industryRow['creditPackageRevenue']);
    }
}
