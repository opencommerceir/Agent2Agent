<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

/**
 * Phase 7/M3 — a plain backed enum, not wrapped in a state machine: unlike
 * VerificationStatus/BusinessStatus (which model a lifecycle with illegal
 * transitions), a role is just a label an Owner reassigns freely. Used only
 * as an Eloquent cast on `business_owners.role` and in Action-level
 * authorization checks — BusinessOwner itself stays "a plain login
 * credential, not a rich Domain entity" (its own docblock, Phase 1/M2).
 *
 * `Owner` is the only role InviteTeamMemberAction/ChangeTeamMemberRoleAction
 * ever protect specially (the last Owner can't be demoted or removed) —
 * Manager/Cfo/Staff exist purely to be matched against Phase 7/M4's
 * ApprovalPolicy levels, no other code branches on them.
 */
enum TeamMemberRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cfo = 'cfo';
    case Staff = 'staff';
}
