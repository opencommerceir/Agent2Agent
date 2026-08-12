<?php

namespace App\Domains\Nexus\Growth\Domain\ValueObjects;

/**
 * Deliberately just two states — no "opened"/"clicked" tracking (that needs
 * a real click-tracking pixel/redirect infrastructure this codebase doesn't
 * have, the same honest-scope call Escrow's own docblock makes about not
 * pretending to hold real money). "Converted" means the invitee actually
 * registered using this Invite's referral code
 * (RecordReferralSignupAction) — a stronger, verifiable signal than mail
 * open/click ever would be.
 */
enum InviteStatus: string
{
    case Sent = 'sent';
    case Converted = 'converted';
}
