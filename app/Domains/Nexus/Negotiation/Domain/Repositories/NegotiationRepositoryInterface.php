<?php

namespace App\Domains\Nexus\Negotiation\Domain\Repositories;

use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;

/**
 * Contract owned by the Domain layer. Deliberately has no
 * findByTenantId()-style method the way every other Nexus repository
 * does — Negotiation is cross-tenant by nature (decision documented on
 * the Negotiation entity itself), so findVisibleTo() authorizes by
 * Business id being either party, not by a single tenant scope.
 */
interface NegotiationRepositoryInterface
{
    public function findById(int $id): ?Negotiation;

    /**
     * @return list<Negotiation>
     */
    public function findVisibleTo(int $businessId): array;

    /**
     * Platform-wide, unscoped — the admin Negotiations Monitor's only
     * caller (an admin isn't a party to any Negotiation, so
     * findVisibleTo() doesn't apply here).
     *
     * @return list<Negotiation>
     */
    public function findAll(): array;

    public function save(Negotiation $negotiation): Negotiation;
}
