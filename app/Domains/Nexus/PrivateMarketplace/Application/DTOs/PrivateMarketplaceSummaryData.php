<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\DTOs;

final class PrivateMarketplaceSummaryData
{
    public function __construct(
        public readonly int $id,
        public readonly string $nameEn,
        public readonly bool $isOwner,
    ) {
    }
}
