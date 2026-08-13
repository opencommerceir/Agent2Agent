<?php

namespace App\Domains\Nexus\Approval\Domain\Repositories;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalDecision;

/**
 * No update()/delete() — the decision log is append-only, same as
 * CreditTransactionRepositoryInterface.
 */
interface ApprovalDecisionRepositoryInterface
{
    /**
     * @return list<ApprovalDecision>
     */
    public function findByApprovalRequestId(int $approvalRequestId): array;

    public function save(ApprovalDecision $decision): ApprovalDecision;
}
