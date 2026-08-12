<?php

namespace App\Domains\Nexus\Credit\Domain\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\CreditPurchaseSession;

interface CreditPurchaseSessionRepositoryInterface
{
    public function findById(int $id, int $businessId): ?CreditPurchaseSession;

    /**
     * Business-**unscoped** lookup by id alone — exists only for the
     * public gateway callback route, which has no authenticated Business
     * owner session at all (same reasoning Commerce's own
     * PaymentSessionRepositoryInterface::findByIdUnscoped() docblock
     * gives). Safe despite the missing scope check: ConfirmCreditPurchaseAction
     * always re-verifies with the gateway's own API before trusting
     * anything.
     */
    public function findByIdUnscoped(int $id): ?CreditPurchaseSession;

    public function save(CreditPurchaseSession $session): CreditPurchaseSession;
}
