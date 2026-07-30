<?php

namespace App\Core\Domain\Events;

use App\Core\Domain\Entities\Organization;

final class OrganizationWasCreated
{
    public function __construct(
        public readonly Organization $organization,
    ) {
    }
}
