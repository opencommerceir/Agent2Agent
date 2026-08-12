<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Application\Actions\RecordReferralSignupAction;
use App\Domains\Nexus\Growth\Application\Actions\SendAgentInviteAction;
use App\Domains\Nexus\Growth\Domain\Repositories\InviteRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the funnel actually closes: an Agent-sent Invite is marked
 * Converted the moment the invited lead registers using the same referral
 * code — even though the registrant's own account email differs from the
 * address the Agent originally emailed (a very common real-world case:
 * the Agent invites a company by its generic sales inbox, a specific
 * person there completes the actual signup).
 */
class InviteConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registeringWithInvitedCode_marksOldestOpenInviteConverted(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 100);
        $invite = app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Co', 'lead@example.com');

        $referee = app(RegisterBusinessAction::class)->execute('نام Referee Co', 'Referee Co', BusinessType::Company, Industry::Technology);
        app(RecordReferralSignupAction::class)->execute($referee->id, app(\App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface::class)->findByBusinessId($inviter->id)->code());

        $stored = app(InviteRepositoryInterface::class)->findById($invite->id);
        $this->assertSame('converted', $stored->status()->value);
        $this->assertSame($referee->id, $stored->convertedBusinessId());
    }

    public function test_multipleOpenInvites_convertOldestFirst(): void
    {
        $inviter = $this->verifiedBusiness('Inviter Co', 100);
        $firstInvite = app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead One', 'one@example.com');
        app(SendAgentInviteAction::class)->execute($inviter->id, 'Lead Two', 'two@example.com');

        $code = app(\App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface::class)->findByBusinessId($inviter->id)->code();
        $referee = app(RegisterBusinessAction::class)->execute('نام Referee Co', 'Referee Co', BusinessType::Company, Industry::Technology);
        app(RecordReferralSignupAction::class)->execute($referee->id, $code);

        $stored = app(InviteRepositoryInterface::class)->findById($firstInvite->id);
        $this->assertSame('converted', $stored->status()->value);
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
