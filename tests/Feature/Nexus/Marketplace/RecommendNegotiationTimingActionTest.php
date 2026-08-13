<?php

namespace Tests\Feature\Nexus\Marketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Marketplace\Application\Actions\RecommendNegotiationTimingAction;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RecommendNegotiationTimingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_unknownCounterparty_throws(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');

        $this->expectException(InvalidArgumentException::class);

        app(RecommendNegotiationTimingAction::class)->execute($caller->id, 999999);
    }

    public function test_execute_noHistory_returnsZeroSampleSize(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $counterparty = $this->verifiedBusiness('Counterparty Co');

        $result = app(RecommendNegotiationTimingAction::class)->execute($caller->id, $counterparty->id);

        $this->assertSame(0, $result['sampleSize']);
        $this->assertSame([], $result['byDayOfWeek']);
    }

    public function test_execute_countsAcceptedAndRejectedDealsAgainstCounterparty(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');

        $accepted = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($accepted->id, $buyer->id);

        $rejected = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 2,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null),
        );
        app(RejectDealAction::class)->execute($rejected->id, $seller->id, null);

        $result = app(RecommendNegotiationTimingAction::class)->execute($buyer->id, $seller->id);

        $this->assertSame(2, $result['sampleSize']);
        $totalAccepted = array_sum(array_column($result['byDayOfWeek'], 'acceptedCount'));
        $this->assertSame(1, $totalAccepted);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
