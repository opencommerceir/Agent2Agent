<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects;

/**
 * Same Invited -> Active|Removed, Active -> Removed shape as
 * HoldingSubsidiary — deliberately not sharing that table despite the
 * identical shape, since the cardinality differs: a Business belongs to at
 * most one Holding but can join many Private Marketplaces.
 */
enum PrivateMarketplaceMemberStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Removed = 'removed';
}
