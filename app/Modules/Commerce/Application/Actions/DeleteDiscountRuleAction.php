<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Exceptions\DiscountRuleNotFoundException;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;

final class DeleteDiscountRuleAction
{
    public function __construct(
        private readonly DiscountRuleRepositoryInterface $rules,
    ) {
    }

    public function execute(int $id, int $tenantId): void
    {
        if (! $this->rules->findById($id, $tenantId)) {
            throw new DiscountRuleNotFoundException("DiscountRule [{$id}] does not exist.");
        }

        $this->rules->delete($id, $tenantId);
    }
}
