<?php

use App\Core\CoreServiceProvider;
use App\Modules\Commerce\CommerceServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    CommerceServiceProvider::class,
];
