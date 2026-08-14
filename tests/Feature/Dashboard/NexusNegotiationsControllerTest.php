<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
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
 * The admin-only, platform-wide Live Negotiation Monitor (CLAUDE.md Admin
 * Panel Must-Haves, #4) — unlike the business-facing Live Negotiation
 * Viewer (NegotiationViewerTest), an admin is never a party to any
 * Negotiation, so these routes must work without either Business's own
 * session and must show every Negotiation, not just one caller's own.
 */
class NexusNegotiationsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameFa, string $nameEn): int
    {
        $business = app(RegisterBusinessAction::class)->execute($nameFa, $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business->id;
    }

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_withoutAdminLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('dashboard.nexus.negotiations.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_listsNegotiationWithBothPartyNames(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $buyerId = $this->verifiedBusiness('شرکت خریدار', 'Buyer Co');
        $sellerId = $this->verifiedBusiness('شرکت فروشنده', 'Seller Co');

        app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.negotiations.index'));

        $response->assertStatus(200);
        $response->assertSee('Buyer Co');
        $response->assertSee('Seller Co');
    }

    public function test_show_displaysMessageThreadAndLinkedContractEscrow(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $buyerId = $this->verifiedBusiness('شرکت خریدار', 'Buyer Co');
        $sellerId = $this->verifiedBusiness('شرکت فروشنده', 'Seller Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyerId);

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.negotiations.show', $negotiation->id));

        $response->assertStatus(200);
        $response->assertSee('Buyer Co');
        $response->assertSee('Seller Co');
        $response->assertSee(t('messages.nexus.negotiation.status.accepted'));
        $response->assertSee(t('messages.nexus.negotiation.escrow.title'));
    }

    public function test_show_withoutContract_showsNoContractYet(): void
    {
        $this->withoutVite();
        $admin = $this->createAdmin();
        $buyerId = $this->verifiedBusiness('شرکت خریدار', 'Buyer Co');
        $sellerId = $this->verifiedBusiness('شرکت فروشنده', 'Seller Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.negotiations.show', $negotiation->id));

        $response->assertStatus(200);
        $response->assertSee(t('messages.nexus.admin.negotiations.contract.none'));
    }

    public function test_show_forNonExistentNegotiation_returns404(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.negotiations.show', 999999));

        $response->assertNotFound();
    }

    public function test_messages_returnsOnlyMessagesAfterGivenId(): void
    {
        $admin = $this->createAdmin();
        $buyerId = $this->verifiedBusiness('شرکت خریدار', 'Buyer Co');
        $sellerId = $this->verifiedBusiness('شرکت فروشنده', 'Seller Co');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyerId, $sellerId, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );

        $response = $this->actingAs($admin)->getJson(route('dashboard.nexus.negotiations.messages', $negotiation->id).'?after=0');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'messages');
    }
}
