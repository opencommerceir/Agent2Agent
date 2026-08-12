<?php

namespace Tests\Feature\Nexus\Negotiation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeEvidenceViewerTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusinessWithOwner(string $nameFa, string $nameEn, string $email): array
    {
        $business = app(RegisterBusinessAction::class)->execute($nameFa, $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => $nameEn.' Owner',
            'email' => $email,
            'password' => 'password123',
        ]);

        return [$business, $owner];
    }

    public function test_submitDisputeEvidence_appendsNoteAndShowsOnPage(): void
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.escrow.dispute', $negotiation->id), ['reason' => 'no delivery']);

        $response = $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.dispute.evidence', $negotiation->id), [
            'note' => 'attached shipping proof',
        ]);
        $response->assertRedirect(route('nexus.negotiations.show', $negotiation->id));

        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiation->id);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());
        $this->assertCount(1, $disputeCase->evidence());

        $show = $this->actingAs($buyerOwner, 'business')->get(route('nexus.negotiations.show', $negotiation->id));
        $show->assertStatus(200);
        $show->assertSee('attached shipping proof');
    }
}
