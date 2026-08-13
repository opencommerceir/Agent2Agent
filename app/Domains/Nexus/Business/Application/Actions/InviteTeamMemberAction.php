<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\TeamMemberData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Phase 7/M3's prerequisite for multi-level Approval Workflows (M4): a
 * Business needs real, distinct, independently-logging-in people (a Manager,
 * a CFO) before "Agent -> Manager -> CFO" can mean anything. BusinessOwner
 * stays "a plain login credential, not a rich Domain entity" (Phase 1/M2's
 * own docblock) — invited via a plain Eloquent create(), same as
 * RegisterBusinessController already does for the first Owner, not a
 * framework-free Domain entity + repository layer this table has never had.
 *
 * Reuses the active Notifications module's SendNotificationAction (Extend,
 * Don't Rebuild) — same reasoning SendAgentInviteAction (Phase 5/M2) already
 * established for a raw-email, no-owning-Customer/Agent send.
 *
 * Documented, bounded shortcut: the temporary password is emailed once in
 * plaintext, not via a secure set-password link/token — acceptable for this
 * phase's scope, same tier as other documented shortcuts in this codebase
 * (e.g. Escrow's state-tracking-not-real-settlement).
 */
final class InviteTeamMemberAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly SendNotificationAction $sendNotification,
    ) {
    }

    public function execute(int $businessId, int $invitedByOwnerId, string $name, string $email, TeamMemberRole $role): TeamMemberData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $inviter = BusinessOwner::query()->find($invitedByOwnerId);

        if (! $inviter || $inviter->business_id !== $businessId || $inviter->role !== TeamMemberRole::Owner) {
            throw new InvalidArgumentException('Only an Owner may invite team members.');
        }

        if (BusinessOwner::query()->where('email', $email)->exists()) {
            throw new InvalidArgumentException("Email [{$email}] is already in use.");
        }

        $temporaryPassword = Str::random(16);

        $owner = BusinessOwner::query()->create([
            'business_id' => $businessId,
            'name' => $name,
            'email' => $email,
            'password' => $temporaryPassword,
            'role' => $role->value,
            'must_change_password' => true,
        ]);

        $this->sendNotification->execute(
            tenantId: $business->tenantId(),
            type: NotificationType::TeamMemberInvited,
            channelType: ChannelType::Email,
            recipient: new Recipient($email),
            subject: "You've been invited to {$business->nameEn()} on Nexus",
            body: "Hi {$name},\n\n"
                ."{$business->nameEn()} invited you to join its team on Nexus as {$role->value}.\n"
                ."Log in at ".route('nexus.business.login')." with this temporary password, you'll be asked to change it: {$temporaryPassword}\n\n"
                ."سلام {$name}،\n"
                ."{$business->nameEn()} شما را به تیم خود در نکسوس با نقش {$role->value} دعوت کرده است.\n"
                ."با این رمز عبور موقت وارد شوید، بعد از ورود از شما خواسته می‌شود رمز را تغییر دهید: {$temporaryPassword}",
            metadata: ['business_owner_id' => $owner->id],
        );

        return TeamMemberData::fromModel($owner);
    }
}
