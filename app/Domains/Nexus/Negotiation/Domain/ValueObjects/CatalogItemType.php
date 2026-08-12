<?php

namespace App\Domains\Nexus\Negotiation\Domain\ValueObjects;

enum CatalogItemType: string
{
    case Product = 'product';
    case Service = 'service';
}
