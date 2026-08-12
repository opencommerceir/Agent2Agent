<?php

namespace Tests\Feature\Nexus\Negotiation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
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

/**
 * The Live Negotiation Viewer's Escrow panel (Phase 3/M4) — "Confirm
 * Delivery"/"Dispute" buttons over the real POST routes, same rigor
 * NegotiationViewerTest already applies to Approve/Reject.
 */
class EscrowViewerTest extends TestCase
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

    private function heldEscrowNegotiation(): array
    {
        [$buyer, $buyerOwner] = $this->verifiedBusinessWithOwner('شرکت خریدار', 'Buyer Co', 'buyer@example.com');
        [$seller] = $this->verifiedBusinessWithOwner('شرکت فروشنده', 'Seller Co', 'seller@example.com');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        return [$negotiation->id, $buyerOwner];
    }

    public function test_show_displaysEscrowPanel(): void
    {
        [$negotiationId, $buyerOwner] = $this->heldEscrowNegotiation();

        $response = $this->actingAs($buyerOwner, 'business')->get(route('nexus.negotiations.show', $negotiationId));

        $response->assertStatus(200);
        $response->assertSee(t('messages.nexus.negotiation.escrow.confirm_delivery'));
    }

    public function test_releaseEscrow_movesToReleased(): void
    {
        [$negotiationId, $buyerOwner] = $this->heldEscrowNegotiation();

        $response = $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.escrow.release', $negotiationId));

        $response->assertRedirect(route('nexus.negotiations.show', $negotiationId));
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $this->assertSame('released', $escrow->status()->value);
    }

    public function test_disputeEscrow_movesToDisputed(): void
    {
        [$negotiationId, $buyerOwner] = $this->heldEscrowNegotiation();

        $response = $this->actingAs($buyerOwner, 'business')->post(route('nexus.negotiations.escrow.dispute', $negotiationId), ['reason' => 'late delivery']);

        $response->assertRedirect(route('nexus.negotiations.show', $negotiationId));
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $this->assertSame('disputed', $escrow->status()->value);
        $this->assertSame('late delivery', $escrow->disputeReason());
    }
}
