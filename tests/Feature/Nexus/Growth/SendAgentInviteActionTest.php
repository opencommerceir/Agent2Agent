<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\SendAgentInviteAction;
use App\Domains\Nexus\Growth\Domain\Repositories\InviteRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use App\Modules\Notifications\Application\Actions\ConfigureChannelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendAgentInviteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_recordsInviteAndDeductsCredit(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 100);

        $invite = app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Co', 'lead@example.com');

        $this->assertSame('sent', $invite->status);
        $this->assertDatabaseHas('nexus_invites', [
            'inviter_business_id' => $inviter->id,
            'invitee_email' => 'lead@example.com',
        ]);

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($inviter->id);
        $this->assertSame(95, $balance->balance());
    }

    public function test_execute_issuesReferralCodeIfMissingAndAttachesItToInvite(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 100);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($inviter->id);

        app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Co', 'lead@example.com');

        $this->assertDatabaseHas('nexus_invites', [
            'inviter_business_id' => $inviter->id,
            'referral_code' => $code->code(),
        ]);
    }

    public function test_execute_withoutConfiguredEmailChannel_stillRecordsInvite(): void
    {
        // No ConfigureChannelAction call — the tenant has no active Email
        // channel. SendNotificationAction must no-op silently, not throw.
        $inviter = $this->verifiedBusiness('Inviter Co', 100);

        $invite = app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Co', 'lead@example.com');

        $this->assertNotNull($invite->id);
        $this->assertDatabaseMissing('notifications', ['channel_type' => 'email']);
    }

    public function test_execute_withConfiguredEmailChannel_sendsNotification(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 100);
        app(ConfigureChannelAction::class)->execute($inviter->tenantId, 'email', [], true);

        app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Co', 'lead@example.com');

        $this->assertDatabaseHas('notifications', [
            'channel_type' => 'email',
            'type' => 'agent_invite',
        ]);
    }

    public function test_execute_withInsufficientCredit_throwsAndDoesNotRecordInvite(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 1);

        $this->expectException(InsufficientCreditException::class);

        try {
            app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Co', 'lead@example.com');
        } finally {
            $this->assertDatabaseMissing('nexus_invites', ['inviter_business_id' => $inviter->id]);
        }
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
