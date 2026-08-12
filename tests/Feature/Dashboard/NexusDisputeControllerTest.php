<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\DisputeEscrowAction;
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

/**
 * Admin-only Dispute Resolution queue (Phase 6/M3) — core `auth`/`admin`
 * guard, same createAdmin() pattern NexusEscrowControllerTest establishes.
 */
class NexusDisputeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    private function openDisputeCaseId(): int
    {
        $buyer = app(RegisterBusinessAction::class)->execute('نام Buyer Co', 'Buyer Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($buyer->id);
        app(GrantCreditsAction::class)->execute($buyer->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');
        $seller = app(RegisterBusinessAction::class)->execute('نام Seller Co', 'Seller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($seller->id);
        app(GrantCreditsAction::class)->execute($seller->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(DisputeEscrowAction::class)->execute($negotiation->id, $buyer->id, 'test dispute reason');

        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiation->id);

        return app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id())->id();
    }

    public function test_index_listsOpenDisputeCases(): void
    {
        $admin = $this->createAdmin();
        $this->openDisputeCaseId();

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.disputes.index'));

        $response->assertStatus(200);
        $response->assertSee('test dispute reason');
    }

    public function test_mediate_movesCaseToMediation(): void
    {
        $admin = $this->createAdmin();
        $disputeId = $this->openDisputeCaseId();

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.disputes.mediate', $disputeId));

        $response->assertRedirect(route('dashboard.nexus.disputes.index'));
        $case = app(DisputeCaseRepositoryInterface::class)->findById($disputeId);
        $this->assertSame('mediation', $case->status()->value);
    }

    public function test_arbitrate_resolvesCaseAndEscrow(): void
    {
        $admin = $this->createAdmin();
        $disputeId = $this->openDisputeCaseId();

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.disputes.arbitrate', $disputeId), ['resolution' => 'refund_buyer']);

        $response->assertRedirect(route('dashboard.nexus.disputes.index'));
        $case = app(DisputeCaseRepositoryInterface::class)->findById($disputeId);
        $this->assertSame('resolved', $case->status()->value);
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.disputes.index'))->assertRedirect('/login');
    }
}
