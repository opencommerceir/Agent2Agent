<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\ContributeToPoolAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Holding\Application\Actions\AcceptSubsidiaryInvitationAction;
use App\Domains\Nexus\Holding\Application\Actions\CreateHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\GetHoldingAction;
use App\Domains\Nexus\Holding\Application\Actions\InviteSubsidiaryAction;
use App\Domains\Nexus\Holding\Application\Actions\SetCreditPoolingEnabledAction;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class HoldingCreditPoolActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contribute_movesCreditFromMemberBalanceToPool(): void
    {
        $parent = $this->verifiedBusiness('Parent Co', 100);
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');

        $pool = app(ContributeToPoolAction::class)->execute($holding->id, $parent->id, 40);

        $this->assertSame(40, $pool->balance);
        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($parent->id);
        $this->assertSame(60, $balance->balance());
        $this->assertDatabaseHas('nexus_holding_credit_pool_transactions', [
            'holding_id' => $holding->id,
            'business_id' => $parent->id,
            'type' => 'pool_contribution',
            'amount' => 40,
        ]);
    }

    public function test_contribute_byNonMember_throws(): void
    {
        $parent = $this->verifiedBusiness('Parent Co', 100);
        $outsider = $this->verifiedBusiness('Outsider Co', 100);
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');

        $this->expectException(InvalidArgumentException::class);

        app(ContributeToPoolAction::class)->execute($holding->id, $outsider->id, 10);
    }

    public function test_contribute_withInsufficientOwnBalance_throwsAndLeavesPoolUntouched(): void
    {
        $parent = $this->verifiedBusiness('Parent Co', 5);
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');

        $this->expectException(InsufficientCreditException::class);

        app(ContributeToPoolAction::class)->execute($holding->id, $parent->id, 40);
    }

    public function test_gatedCapability_withPoolingEnabled_spendsFromPoolNotOwnBalance(): void
    {
        $parent = $this->verifiedBusiness('Parent Co', 100);
        $sub = $this->verifiedBusiness('Sub Co', 100);
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        $this->joinAsActiveSubsidiary($holding->id, $parent->id, $sub->id);
        app(SetCreditPoolingEnabledAction::class)->execute($holding->id, $parent->id, true);
        app(ContributeToPoolAction::class)->execute($holding->id, $parent->id, 50);
        // parent balance: 100 - 50 (contribution) = 50

        app(SearchMarketplaceAction::class)->execute($sub->id);
        // nexus.marketplace.search costs 5 (config/nexus/platform.php default)

        $pool = app(HoldingCreditPoolRepositoryInterface::class)->findByHoldingId($holding->id);
        $this->assertSame(45, $pool->balance());

        $subBalance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($sub->id);
        $this->assertSame(100, $subBalance->balance(), 'the subsidiary\'s own balance must be untouched');

        $this->assertDatabaseHas('nexus_holding_credit_pool_transactions', [
            'holding_id' => $holding->id,
            'business_id' => $sub->id,
            'type' => 'pool_deduction',
            'reason' => 'nexus.marketplace.search',
            'amount' => 5,
        ]);
    }

    public function test_gatedCapability_withPoolingDisabled_stillSpendsFromOwnBalance(): void
    {
        $parent = $this->verifiedBusiness('Parent Co', 100);
        $sub = $this->verifiedBusiness('Sub Co', 100);
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        $this->joinAsActiveSubsidiary($holding->id, $parent->id, $sub->id);
        app(ContributeToPoolAction::class)->execute($holding->id, $parent->id, 50);
        // pooling left disabled (default)

        app(SearchMarketplaceAction::class)->execute($sub->id);

        $subBalance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($sub->id);
        $this->assertSame(95, $subBalance->balance());
        $pool = app(HoldingCreditPoolRepositoryInterface::class)->findByHoldingId($holding->id);
        $this->assertSame(50, $pool->balance(), 'pool must be untouched while pooling is disabled');
    }

    public function test_gatedCapability_withPoolingEnabledButInsufficientPool_throws(): void
    {
        $parent = $this->verifiedBusiness('Parent Co', 100);
        $sub = $this->verifiedBusiness('Sub Co', 100);
        $holding = app(CreateHoldingAction::class)->execute($parent->id, 'الف', 'Alpha');
        $this->joinAsActiveSubsidiary($holding->id, $parent->id, $sub->id);
        app(SetCreditPoolingEnabledAction::class)->execute($holding->id, $parent->id, true);
        // pool never funded — balance 0

        $this->expectException(InsufficientCreditException::class);

        app(SearchMarketplaceAction::class)->execute($sub->id);
    }

    private function joinAsActiveSubsidiary(int $holdingId, int $parentId, int $subBusinessId): void
    {
        app(InviteSubsidiaryAction::class)->execute($holdingId, $parentId, $subBusinessId);
        $holding = app(GetHoldingAction::class)->execute($holdingId);
        $subsidiaryId = collect($holding->subsidiaries)->firstWhere('businessId', $subBusinessId)['id'];
        app(AcceptSubsidiaryInvitationAction::class)->execute($subsidiaryId, $subBusinessId);
    }

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        if ($credits > 0) {
            app(GrantCreditsAction::class)->execute($business->id, $credits, CreditTransactionType::AdminGrant, 'test.seed');
        }

        return $business;
    }
}
