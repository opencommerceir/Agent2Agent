<?php

use App\Core\CoreServiceProvider;
use App\Modules\Commerce\CommerceServiceProvider;
use App\Modules\CRM\CRMServiceProvider;
use App\Modules\Demo\DemoServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Modules\Loyalty\LoyaltyServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
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
    // Registered after Commerce/Loyalty/Shipping — Notifications' own
    // Listeners depend on Domain Events those providers' modules
    // dispatch; Laravel runs every provider's register() before any
    // boot() regardless of this ordering (same mechanics
    // Finance/Commerce's TaxRateProviderInterface rebind already relies
    // on, HANDOFF §7.8), so this ordering is a readability choice, not a
    // correctness requirement.
    NotificationsServiceProvider::class,
    DemoServiceProvider::class,
];
