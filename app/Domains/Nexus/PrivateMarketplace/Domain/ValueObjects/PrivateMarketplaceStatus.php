<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects;

enum PrivateMarketplaceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
