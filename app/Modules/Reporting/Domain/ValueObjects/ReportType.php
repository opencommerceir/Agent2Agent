<?php

namespace App\Modules\Reporting\Domain\ValueObjects;

enum ReportType: string
{
    case Sales = 'sales';
    case TopProducts = 'top_products';
    case TopCustomers = 'top_customers';
    case Revenue = 'revenue';
    case Loyalty = 'loyalty';
}
