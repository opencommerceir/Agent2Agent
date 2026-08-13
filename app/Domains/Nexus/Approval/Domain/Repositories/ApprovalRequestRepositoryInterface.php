<?php

namespace App\Domains\Nexus\Approval\Domain\Repositories;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalRequest;

interface ApprovalRequestRepositoryInterface
{
    public function findById(int $id): ?ApprovalRequest;

    public function findByNegotiationId(int $negotiationId): ?ApprovalRequest;

    public function save(ApprovalRequest $request): ApprovalRequest;
}
