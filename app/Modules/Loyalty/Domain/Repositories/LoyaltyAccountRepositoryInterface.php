<?php

namespace App\Modules\Loyalty\Domain\Repositories;

use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount;
use App\Modules\Loyalty\Domain\Entities\Redemption;

/**
 * Contract owned by the Domain layer (Interfaces Over Tight Coupling).
 * Every method takes tenantId explicitly — never inferred from ambient
 * state. Also owns Redemption persistence (saveRedemption()/
 * listRedemptions()) — a Redemption has no meaning detached from the
 * LoyaltyAccount that spent the points, the same "repository interface
 * owns its child records" shape Workflows' WorkflowRepositoryInterface
 * (owns WorkflowLog) and Finance's InvoiceRepositoryInterface (owns
 * InvoiceItem) already established — see Redemption's own docblock.
 */
interface LoyaltyAccountRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?LoyaltyAccount;

    public function findByCustomer(int $customerId, int $tenantId): ?LoyaltyAccount;

    public function customerHasAccount(int $customerId, int $tenantId): bool;

    /**
     * Added for BulkExpirePointsAction (the scheduled `loyalty:expire-points`
     * command, HANDOFF §8.23/§8.27): ExpirePointsAction itself only ever
     * processes one LoyaltyAccount at a time (its own docblock already
     * calls this "the natural unit a future scheduled job would iterate
     * accounts and call this once per account") — nothing before this
     * could enumerate every account for a tenant to iterate over.
     *
     * @return list<LoyaltyAccount>
     */
    public function allForTenant(int $tenantId): array;

    public function save(LoyaltyAccount $account): LoyaltyAccount;

    public function saveRedemption(Redemption $redemption): Redemption;

    /**
     * @return list<Redemption>
     */
    public function listRedemptions(int $loyaltyAccountId, int $tenantId, int $limit): array;
}
