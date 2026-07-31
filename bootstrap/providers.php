<?php

use App\Core\CoreServiceProvider;
use App\Modules\Commerce\CommerceServiceProvider;
use App\Modules\CRM\CRMServiceProvider;
use App\Modules\Demo\DemoServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Modules\Loyalty\LoyaltyServiceProvider;
use App\Modules\Reporting\ReportingServiceProvider;
use App\Modules\Shipping\ShippingServiceProvider;
use App\Modules\Workflows\WorkflowsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    CommerceServiceProvider::class,
    CRMServiceProvider::class,
    FinanceServiceProvider::class,
    WorkflowsServiceProvider::class,
    LoyaltyServiceProvider::class,
    ReportingServiceProvider::class,
    ShippingServiceProvider::class,
    DemoServiceProvider::class,
];
