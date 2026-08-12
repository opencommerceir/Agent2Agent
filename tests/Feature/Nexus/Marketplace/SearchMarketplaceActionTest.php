<?php

namespace Tests\Feature\Nexus\Marketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Catalog\Application\Actions\VerifyProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchMarketplaceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_onlyReturnsVerifiedBusinessesExcludingCaller(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        // Phase 3/M2's CostGate now gates nexus.marketplace.search — a
        // generous flat top-up so this domain's own tests keep exercising
        // search mechanics, not credit exhaustion.
        app(GrantCreditsAction::class)->execute($caller->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        $verified = app(RegisterBusinessAction::class)->execute('تأیید شده', 'Verified Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($verified->id);

        $unverified = app(RegisterBusinessAction::class)->execute('تأیید نشده', 'Unverified Co', BusinessType::Company, Industry::Retail);

        $result = app(SearchMarketplaceAction::class)->execute($caller->id);

        $businessIds = array_column($result['listings'], 'businessId');
        $this->assertContains($verified->id, $businessIds);
        $this->assertNotContains($unverified->id, $businessIds);
        $this->assertNotContains($caller->id, $businessIds);
    }

    public function test_execute_withQuery_matchesCatalogItemName(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        // Phase 3/M2's CostGate now gates nexus.marketplace.search — a
        // generous flat top-up so this domain's own tests keep exercising
        // search mechanics, not credit exhaustion.
        app(GrantCreditsAction::class)->execute($caller->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        $supplier = app(RegisterBusinessAction::class)->execute('تأمین‌کننده', 'Supplier Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($supplier->id);
        app(AddProductAction::class)->execute($supplier->id, 'لپ‌تاپ', 'Laptop', 5000000, 'IRT', 10);

        $result = app(SearchMarketplaceAction::class)->execute($caller->id, 'Laptop');

        $this->assertCount(1, $result['listings']);
        $this->assertSame($supplier->id, $result['listings'][0]['businessId']);
        $this->assertSame('Laptop', $result['listings'][0]['products'][0]['nameEn']);
    }

    public function test_execute_withIndustryFilter_onlyMatchesThatIndustry(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        // Phase 3/M2's CostGate now gates nexus.marketplace.search — a
        // generous flat top-up so this domain's own tests keep exercising
        // search mechanics, not credit exhaustion.
        app(GrantCreditsAction::class)->execute($caller->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        $retail = app(RegisterBusinessAction::class)->execute('خرده‌فروش', 'Retail Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($retail->id);

        $tech = app(RegisterBusinessAction::class)->execute('فناوری', 'Tech Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($tech->id);

        $result = app(SearchMarketplaceAction::class)->execute($caller->id, null, 'retail');

        $businessIds = array_column($result['listings'], 'businessId');
        $this->assertContains($retail->id, $businessIds);
        $this->assertNotContains($tech->id, $businessIds);
    }

    public function test_execute_listingsCarryTheirVerificationStatus(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        app(GrantCreditsAction::class)->execute($caller->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        $supplier = app(RegisterBusinessAction::class)->execute('تأمین‌کننده', 'Supplier Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($supplier->id);
        $verifiedProduct = app(AddProductAction::class)->execute($supplier->id, 'لپ‌تاپ', 'Laptop', 5000000, 'IRT', 10);
        app(VerifyProductAction::class)->execute($verifiedProduct->id);
        app(AddProductAction::class)->execute($supplier->id, 'ماوس', 'Mouse', 100000, 'IRT', 10);

        $result = app(SearchMarketplaceAction::class)->execute($caller->id);

        $products = collect($result['listings'][0]['products'])->keyBy('nameEn');
        $this->assertTrue($products['Laptop']['verified']);
        $this->assertFalse($products['Mouse']['verified']);
    }
}
