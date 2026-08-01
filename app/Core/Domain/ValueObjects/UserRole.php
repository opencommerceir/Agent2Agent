<?php

namespace App\Core\Domain\ValueObjects;

/**
 * Not named in Phase 4 Stage 5's own request, but its own Authorization
 * rule ("only a User with role admin may reach the Dashboard") needs a
 * type-safe representation — a raw string column would let an invalid
 * value slip in silently, the same reasoning `OrganizationMemberRole`'s
 * own docblock already gives for an identical gap.
 *
 * Deliberately NOT the tenant-scoped Role/Permission/MemberRole RBAC
 * system Agents use for MCP capability authorization: that system grants
 * a Role *inside one specific Tenant's Organization*. A Dashboard User
 * manages the platform itself (Tenant Management is a Dashboard page —
 * an operator creating/editing *other businesses' own tenants*, not a
 * capability scoped to one), so it is platform-level, like `Tenant` itself
 * ("the only Core entity that does not carry a tenant_id" — User is the
 * second). `Admin` can reach every Dashboard page; `Operator` is modeled
 * for a future, more restricted staff role but is not given any narrower
 * access this stage — nothing requested that distinction yet.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
}
