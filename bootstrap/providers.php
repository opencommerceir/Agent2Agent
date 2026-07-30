<?php

use App\Core\CoreServiceProvider;
use App\Modules\Commerce\CommerceServiceProvider;
use App\Modules\Demo\DemoServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    CommerceServiceProvider::class,
    DemoServiceProvider::class,
];
