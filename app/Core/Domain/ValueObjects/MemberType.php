<?php

namespace App\Core\Domain\ValueObjects;

/**
 * Identifies which polymorphic aggregate a member_id refers to
 * (organization_members.member_type, member_roles.member_type).
 */
enum MemberType: string
{
    case User = 'user';
    case Agent = 'agent';
}
