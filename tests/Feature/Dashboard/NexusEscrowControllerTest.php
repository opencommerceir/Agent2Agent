<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
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
 * Admin-only Escrow dispute resolution (Phase 3/M4) — core `auth`/`admin`
 * guard, same createAdmin() pattern PerformancePageTest already
 * establishes, never the `business.auth` guard.
 */
class NexusEscrowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    private function disputedEscrowId(): int
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

        $escrows = app(EscrowRepositoryInterface::class);
        $escrow = $escrows->findByNegotiationId($negotiation->id);
        $escrow->dispute('test dispute');
        $escrow = $escrows->save($escrow);

        return $escrow->id();
    }

    public function test_index_listsDisputedEscrows(): void
    {
        $admin = $this->createAdmin();
        $this->disputedEscrowId();

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.escrows.index'));

        $response->assertStatus(200);
        $response->assertSee('test dispute');
    }

    public function test_refund_marksEscrowRefunded(): void
    {
        $admin = $this->createAdmin();
        $escrowId = $this->disputedEscrowId();

        $response = $this->actingAs($admin)->post(route('dashboard.nexus.escrows.refund', $escrowId));

        $response->assertRedirect(route('dashboard.nexus.escrows.index'));
        $escrow = app(EscrowRepositoryInterface::class)->findById($escrowId);
        $this->assertSame('refunded', $escrow->status()->value);
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.escrows.index'))->assertRedirect('/login');
    }
}
