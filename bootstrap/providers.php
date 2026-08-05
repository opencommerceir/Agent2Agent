<?php

use App\Core\CoreServiceProvider;
use App\Modules\AgentOrchestrator\AgentOrchestratorServiceProvider;
use App\Modules\Analytics\AnalyticsServiceProvider;
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
    // Registered after Reporting — Analytics' own CalculateKPIAction
    // depends directly on Reporting's Infrastructure\Queries\* Query
    // Builders (plain autowired concrete classes, not bound here either
    // way), a readability choice mirroring Notifications' own comment
    // below, not a correctness requirement (register() always runs for
    // every provider before any boot()).
    AnalyticsServiceProvider::class,
    ShippingServiceProvider::class,
    // Registered after Commerce/Loyalty/Shipping — Notifications' own
    // Listeners depend on Domain Events those providers' modules
    // dispatch; Laravel runs every provider's register() before any
    // boot() regardless of this ordering (same mechanics
    // Finance/Commerce's TaxRateProviderInterface rebind already relies
    // on, HANDOFF §7.8), so this ordering is a readability choice, not a
    // correctness requirement.
    NotificationsServiceProvider::class,
    // Registered last — the Agent Orchestrator's own CapabilityToolInvoker
    // re-enters the Capability Registry every other module's boot() has
    // already populated by this point (Laravel still runs every
    // provider's register() before any boot() regardless of this
    // ordering, the same mechanics Finance/Commerce's
    // TaxRateProviderInterface rebind already relies on, HANDOFF §7.8) —
    // a readability choice, not a correctness requirement.
    AgentOrchestratorServiceProvider::class,
    DemoServiceProvider::class,
];
