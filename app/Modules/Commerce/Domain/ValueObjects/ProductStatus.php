<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
