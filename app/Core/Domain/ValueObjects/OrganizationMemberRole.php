<?php

namespace App\Core\Domain\ValueObjects;

/**
 * The member's standing *inside a specific organization* (governance
 * position — owner/admin/member). Deliberately distinct from the Role
 * entity: this is a fixed 3-value membership label, while Role/Permission
 * is an open-ended, tenant-defined RBAC system. Not explicitly requested,
 * but "role_in_org" needs a type-safe representation per the Type Safety
 * rule — a raw string column would let an invalid value slip in silently.
 */
enum OrganizationMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}
