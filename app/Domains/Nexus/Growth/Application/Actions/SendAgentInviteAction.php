<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Growth\Application\DTOs\InviteData;
use App\Domains\Nexus\Growth\Domain\Entities\Invite;
use App\Domains\Nexus\Growth\Domain\Repositories\InviteRepositoryInterface;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use InvalidArgumentException;

/**
 * The roadmap's "Agent-Invites-Agent": an Agent, via MCP
 * (nexus.invite.send), asks the platform to invite a lead it found outside
 * the platform (a prospective counterparty it couldn't negotiate with
 * because they aren't a member yet). Reuses the Notifications module's own
 * SendNotificationAction (Extend, Don't Rebuild) rather than a second email
 * pipeline — a raw external email has no owning Customer/Agent id, so
 * $recipientType/$recipientId stay null (SendNotificationAction's own
 * docblock: preference-checking is skipped entirely for that shape, which
 * is exactly right here — there is structurally no Preference row for a
 * lead who isn't in the system yet).
 *
 * The invite always carries the inviter's own ReferralCode (M1) baked into
 * the registration link — conversion crediting is entirely the existing
 * Referral flow's job (RecordReferralSignupAction /
 * GrantReferralRewardOnBusinessVerifiedListener); this Action only records
 * the funnel entry and best-effort sends the email. A missing/inactive
 * Email NotificationChannel for the inviter's tenant makes
 * SendNotificationAction a silent no-op (its own dispatcher rule) — the
 * Invite row is still recorded either way, since the growth-funnel record
 * (and the CostGate charge for using the Agent's time to compose it) is
 * the honest unit of "an invite was sent," not raw SMTP delivery.
 */
final class SendAgentInviteAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly InviteRepositoryInterface $invites,
        private readonly IssueReferralCodeAction $issueReferralCode,
        private readonly SendNotificationAction $sendNotification,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function execute(
        int $inviterBusinessId,
        string $inviteeName,
        string $inviteeEmail,
        string $messageVariant = 'a',
    ): InviteData {
        $inviter = $this->businesses->findById($inviterBusinessId);

        if (! $inviter) {
            throw new InvalidArgumentException("Business [{$inviterBusinessId}] does not exist.");
        }

        $this->costGate->execute($inviterBusinessId, 'nexus.invite.send');

        $code = $this->issueReferralCode->execute($inviterBusinessId);
        $registerUrl = route('nexus.business.register', ['ref' => $code->code()]);

        $invite = $this->invites->save(
            Invite::send($inviterBusinessId, $inviteeName, $inviteeEmail, $code->code(), $messageVariant)
        );

        $this->sendNotification->execute(
            tenantId: $inviter->tenantId(),
            type: NotificationType::AgentInvite,
            channelType: ChannelType::Email,
            recipient: new Recipient($inviteeEmail),
            subject: $this->subject($inviter->nameEn()),
            body: $this->body($inviter->nameEn(), $inviteeName, $registerUrl),
            metadata: ['invite_id' => $invite->id(), 'referral_code' => $code->code()],
        );

        return InviteData::fromEntity($invite);
    }

    private function subject(string $inviterNameEn): string
    {
        return "{$inviterNameEn} invited you to Nexus";
    }

    private function body(string $inviterNameEn, string $inviteeName, string $registerUrl): string
    {
        return "Hi {$inviteeName},\n\n"
            ."{$inviterNameEn}'s Agent invited your business to join Nexus, the Agent-to-Agent commerce platform.\n"
            ."Register here to get started: {$registerUrl}\n\n"
            ."سلام {$inviteeName}،\n"
            ."ایجنت {$inviterNameEn} شما را به نکسوس، پلتفرم تجارت ایجنت‌به‌ایجنت، دعوت کرده است.\n"
            ."برای شروع ثبت‌نام کنید: {$registerUrl}";
    }
}
