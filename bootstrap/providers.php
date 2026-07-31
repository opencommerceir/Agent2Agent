<?php

use App\Core\CoreServiceProvider;
use App\Modules\Commerce\CommerceServiceProvider;
use App\Modules\CRM\CRMServiceProvider;
use App\Modules\Demo\DemoServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    CommerceServiceProvider::class,
    CRMServiceProvider::class,
    FinanceServiceProvider::class,
    DemoServiceProvider::class,
];
