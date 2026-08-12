<?php

namespace App\Domains\Nexus;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Domains\Nexus\Admin\Domain\Repositories\PlatformSettingRepositoryInterface;
use App\Domains\Nexus\Admin\Infrastructure\Repositories\EloquentPlatformSettingRepository;
use App\Domains\Nexus\Agent\Application\Actions\ResolveActingBusinessAction;
use App\Domains\Nexus\Agent\Application\Listeners\CreateAgentOnBusinessVerifiedListener;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Agent\Infrastructure\Repositories\EloquentAgentRepository;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Infrastructure\Repositories\EloquentBusinessRepository;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Infrastructure\Repositories\EloquentProductRepository;
use App\Domains\Nexus\Catalog\Infrastructure\Repositories\EloquentServiceRepository;
use App\Domains\Nexus\Contract\Application\Listeners\GenerateContractOnNegotiationAcceptedListener;
use App\Domains\Nexus\Contract\Application\Listeners\HoldEscrowOnContractGeneratedListener;
use App\Domains\Nexus\Contract\Domain\Events\ContractWasGenerated;
use App\Domains\Nexus\Contract\Domain\Repositories\ContractRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Contract\Infrastructure\Repositories\EloquentContractRepository;
use App\Domains\Nexus\Contract\Infrastructure\Repositories\EloquentEscrowRepository;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Credit\Application\Listeners\GrantStartingCreditsOnBusinessVerifiedListener;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentCreditBalanceRepository;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentCreditPurchaseSessionRepository;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentCreditTransactionRepository;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\GetNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\SendCounterOfferAction;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money as NegotiationMoney;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;
use App\Domains\Nexus\Negotiation\Infrastructure\Repositories\EloquentNegotiationMessageRepository;
use App\Domains\Nexus\Negotiation\Infrastructure\Repositories\EloquentNegotiationRepository;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Application\Services\StripeConfig;
use App\Modules\Commerce\Application\Services\StripePaymentGateway;
use App\Modules\Commerce\Application\Services\ZibalConfig;
use App\Modules\Commerce\Application\Services\ZibalPaymentGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Nexus's own infrastructure (config, routes, views, migrations)
 * plus each domain's repository bindings, the same "one provider per
 * module" shape Core's own CoreServiceProvider uses.
 */
class NexusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('nexus/platform.php'), 'nexus.platform');

        $this->app->bind(PlatformSettingRepositoryInterface::class, EloquentPlatformSettingRepository::class);
        $this->app->bind(BusinessRepositoryInterface::class, EloquentBusinessRepository::class);
        $this->app->bind(AgentRepositoryInterface::class, EloquentAgentRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, EloquentServiceRepository::class);
        $this->app->bind(NegotiationRepositoryInterface::class, EloquentNegotiationRepository::class);
        $this->app->bind(NegotiationMessageRepositoryInterface::class, EloquentNegotiationMessageRepository::class);
        $this->app->bind(ContractRepositoryInterface::class, EloquentContractRepository::class);
        $this->app->bind(EscrowRepositoryInterface::class, EloquentEscrowRepository::class);
        $this->app->bind(CreditBalanceRepositoryInterface::class, EloquentCreditBalanceRepository::class);
        $this->app->bind(CreditTransactionRepositoryInterface::class, EloquentCreditTransactionRepository::class);
        $this->app->bind(CreditPurchaseSessionRepositoryInterface::class, EloquentCreditPurchaseSessionRepository::class);

        // Nexus's own PaymentGatewayRegistry singleton — CommerceServiceProvider
        // (where these adapter classes originally live) is disabled since
        // Nexus Phase 0, so its own registry is never booted; Nexus
        // registers the same adapter classes under its own instance
        // instead of reimplementing the Zibal/Stripe HTTP integration.
        $this->app->singleton(PaymentGatewayRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/nexus/web.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/api.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/mcp.php'));

        $this->loadViewsFrom(resource_path('views/nexus'), 'nexus');
        $this->loadMigrationsFrom(database_path('migrations/nexus'));

        Event::listen(BusinessWasVerified::class, CreateAgentOnBusinessVerifiedListener::class);
        Event::listen(BusinessWasVerified::class, GrantStartingCreditsOnBusinessVerifiedListener::class);
        Event::listen(NegotiationWasAccepted::class, GenerateContractOnNegotiationAcceptedListener::class);
        Event::listen(ContractWasGenerated::class, HoldEscrowOnContractGeneratedListener::class);

        // Real Payment Gateways (Phase 3/M3) — same Connector Pattern
        // CommerceServiceProvider's own (dead, since Commerce is disabled)
        // wiring already established; only 'zibal' is actually reachable
        // from PurchaseCreditsAction today (Toman-priced packages), but
        // 'stripe' is registered too so the connector wiring itself is
        // proven for a future non-IRT package set.
        $gateways = $this->app->make(PaymentGatewayRegistry::class);
        $gateways->register('zibal', new ZibalPaymentGateway(ZibalConfig::fromConfig()));
        $gateways->register('stripe', new StripePaymentGateway(StripeConfig::fromConfig()));

        $this->registerMcpCapabilityHandlers();
    }

    /**
     * Wires each Nexus MCP capability's real handler into the Capability
     * Registry — the registry metadata itself is seeded separately
     * (database/seeders/Nexus*CapabilitiesSeeder.php), since
     * RefreshDatabase migrates *after* providers boot (same reasoning
     * CommerceServiceProvider's own wiring follows).
     */
    private function registerMcpCapabilityHandlers(): void
    {
        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('nexus.marketplace.search', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(SearchMarketplaceAction::class)->execute(
                callingBusinessId: $callingBusinessId,
                query: $input['query'] ?? null,
                industry: $input['industry'] ?? null,
            );
        });

        $handlers->register('nexus.credit.balance', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $balance = $this->app->make(GetCreditBalanceAction::class)->execute($callingBusinessId);

            return $balance->toArray();
        });

        $this->registerNegotiationCapabilityHandlers($handlers);
    }

    private function registerNegotiationCapabilityHandlers(CapabilityHandlerRegistry $handlers): void
    {
        $handlers->register('nexus.negotiation.propose', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $negotiation = $this->app->make(InitiateNegotiationAction::class)->execute(
                initiatorBusinessId: $callingBusinessId,
                counterpartyBusinessId: (int) $input['counterparty_business_id'],
                catalogItemType: CatalogItemType::from($input['catalog_item_type']),
                catalogItemId: (int) $input['catalog_item_id'],
                terms: new NegotiationTerms(
                    NegotiationMoney::fromAmount((int) $input['price_amount'], $input['price_currency']),
                    (int) ($input['quantity'] ?? 1),
                    $input['notes'] ?? null,
                ),
            );

            return ['negotiation' => $negotiation->toArray()];
        });

        $handlers->register('nexus.negotiation.counter', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $negotiation = $this->app->make(SendCounterOfferAction::class)->execute(
                negotiationId: (int) $input['negotiation_id'],
                actingBusinessId: $callingBusinessId,
                terms: new NegotiationTerms(
                    NegotiationMoney::fromAmount((int) $input['price_amount'], $input['price_currency']),
                    (int) ($input['quantity'] ?? 1),
                    $input['notes'] ?? null,
                ),
            );

            return ['negotiation' => $negotiation->toArray()];
        });

        $handlers->register('nexus.negotiation.accept', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $negotiation = $this->app->make(AcceptDealAction::class)->execute((int) $input['negotiation_id'], $callingBusinessId);

            return ['negotiation' => $negotiation->toArray()];
        });

        $handlers->register('nexus.negotiation.reject', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $negotiation = $this->app->make(RejectDealAction::class)->execute(
                (int) $input['negotiation_id'],
                $callingBusinessId,
                $input['reason'] ?? null,
            );

            return ['negotiation' => $negotiation->toArray()];
        });

        $handlers->register('nexus.negotiation.status', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $negotiation = $this->app->make(GetNegotiationAction::class)->execute((int) $input['negotiation_id'], $callingBusinessId);

            return ['negotiation' => $negotiation->toArray()];
        });
    }

    private function resolveActingBusiness(AuthContext $context): int
    {
        return $this->app->make(ResolveActingBusinessAction::class)->execute($context->agentId);
    }
}
