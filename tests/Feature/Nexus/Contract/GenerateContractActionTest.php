<?php

namespace Tests\Feature\Nexus\Contract;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Domain\Repositories\ContractRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proves the whole "Negotiation accepted -> Contract auto-generated"
 * pipeline end to end (GenerateContractOnNegotiationAcceptedListener
 * reacting to the real NegotiationWasAccepted event, not called
 * directly) — including that dompdf actually renders Persian (RTL)
 * business names without throwing, the one real risk this milestone's
 * own plan flagged (barryvdh/laravel-dompdf's loadView() had never been
 * exercised anywhere in this codebase before).
 */
class GenerateContractActionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameFa, string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute($nameFa, $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        // Phase 3/M2's CostGate now gates propose/accept (and, via
        // GenerateContractOnNegotiationAcceptedListener, the initiator's
        // contract.generate charge too) — a generous flat top-up so this
        // pipeline test keeps exercising contract generation, not credit
        // exhaustion.
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    public function test_acceptingANegotiation_autoGeneratesASignedContractWithPdf(): void
    {
        Storage::fake('public');

        $buyer = $this->verifiedBusiness('شرکت خریدار', 'Buyer Co');
        $seller = $this->verifiedBusiness('شرکت فروشنده', 'Seller Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(150000, 'IRT'), 2, null),
        );

        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $contract = app(ContractRepositoryInterface::class)->findByNegotiationId($negotiation->id);

        $this->assertNotNull($contract);
        $this->assertSame($buyer->id, $contract->businessAId());
        $this->assertSame($seller->id, $contract->businessBId());
        $this->assertSame(64, strlen($contract->contentHash()));
        $this->assertSame($contract->contentHash(), hash('sha256', json_encode($contract->terms(), JSON_THROW_ON_ERROR)));
        $this->assertNotNull($contract->pdfPath());

        Storage::disk('public')->assertExists($contract->pdfPath());
        $pdfBytes = Storage::disk('public')->get($contract->pdfPath());
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertGreaterThan(1000, strlen($pdfBytes));
    }

    public function test_rejectingANegotiation_generatesNoContract(): void
    {
        $buyer = $this->verifiedBusiness('شرکت خریدار', 'Buyer Co');
        $seller = $this->verifiedBusiness('شرکت فروشنده', 'Seller Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(150000, 'IRT'), 1, null),
        );

        app(RejectDealAction::class)->execute($negotiation->id, $seller->id);

        $contract = app(ContractRepositoryInterface::class)->findByNegotiationId($negotiation->id);

        $this->assertNull($contract);
    }
}
