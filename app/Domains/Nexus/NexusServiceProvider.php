<?php

namespace App\Domains\Nexus;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Domains\Nexus\Admin\Domain\Repositories\PlatformSettingRepositoryInterface;
use App\Domains\Nexus\Analytics\Application\Actions\AssessDealRiskAction;
use App\Domains\Nexus\Analytics\Application\Actions\ForecastSupplierReliabilityAction;
use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessAnalyticsAction;
use App\Domains\Nexus\Analytics\Application\Actions\GetMarketIntelligenceAction;
use App\Domains\Nexus\Analytics\Application\Actions\SimulateNegotiationScenarioAction;
use App\Domains\Nexus\Admin\Infrastructure\Repositories\EloquentPlatformSettingRepository;
use App\Domains\Nexus\Agent\Application\Actions\ResolveActingBusinessAction;
use App\Domains\Nexus\Automation\Application\Actions\CreateInventoryAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreatePriceAlertRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\CreateRecurringOrderRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\DeleteAutomationRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\ListAutomationRulesAction;
use App\Domains\Nexus\Automation\Application\Actions\PauseAutomationRuleAction;
use App\Domains\Nexus\Automation\Application\Actions\ResumeAutomationRuleAction;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRunLogRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Automation\Infrastructure\Repositories\EloquentAutomationRuleRepository;
use App\Domains\Nexus\Automation\Infrastructure\Repositories\EloquentAutomationRunLogRepository;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalDecisionRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalPolicyRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalRequestRepositoryInterface;
use App\Domains\Nexus\Approval\Infrastructure\Repositories\EloquentApprovalDecisionRepository;
use App\Domains\Nexus\Approval\Infrastructure\Repositories\EloquentApprovalPolicyRepository;
use App\Domains\Nexus\Approval\Infrastructure\Repositories\EloquentApprovalRequestRepository;
use App\Domains\Nexus\Agent\Application\Listeners\CreateAgentOnBusinessVerifiedListener;
use App\Domains\Nexus\Audit\Application\Services\AuditingCapabilityHandlerRegistry;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;
use App\Domains\Nexus\Audit\Infrastructure\Repositories\EloquentAuditLogEntryRepository;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Agent\Infrastructure\Repositories\EloquentAgentRepository;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionAppealRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionRecordRepositoryInterface;
use App\Domains\Nexus\Business\Infrastructure\Repositories\EloquentBusinessRepository;
use App\Domains\Nexus\Business\Infrastructure\Repositories\EloquentSuspensionAppealRepository;
use App\Domains\Nexus\Business\Infrastructure\Repositories\EloquentSuspensionRecordRepository;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Infrastructure\Repositories\EloquentProductRepository;
use App\Domains\Nexus\Catalog\Infrastructure\Repositories\EloquentServiceRepository;
use App\Domains\Nexus\Contract\Application\Listeners\GenerateContractOnNegotiationAcceptedListener;
use App\Domains\Nexus\Contract\Application\Listeners\HoldEscrowOnContractGeneratedListener;
use App\Domains\Nexus\Contract\Application\Listeners\OpenDisputeCaseOnEscrowDisputedListener;
use App\Domains\Nexus\Contract\Domain\Events\ContractWasGenerated;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasDisputed;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasReleased;
use App\Domains\Nexus\Contract\Domain\Repositories\ContractRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Contract\Infrastructure\Repositories\EloquentContractRepository;
use App\Domains\Nexus\Contract\Infrastructure\Repositories\EloquentDisputeCaseRepository;
use App\Domains\Nexus\Contract\Infrastructure\Repositories\EloquentEscrowRepository;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Developer\Application\Listeners\DispatchWebhookOnContractGeneratedListener;
use App\Domains\Nexus\Developer\Application\Listeners\DispatchWebhookOnEscrowReleasedListener;
use App\Domains\Nexus\Developer\Application\Listeners\DispatchWebhookOnNegotiationAcceptedListener;
use App\Domains\Nexus\Developer\Domain\Repositories\ApiKeyRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentStrategyTemplateRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentTemplateInstallRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookDeliveryLogRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;
use App\Domains\Nexus\Developer\Infrastructure\Repositories\EloquentAgentStrategyTemplateRepository;
use App\Domains\Nexus\Developer\Infrastructure\Repositories\EloquentAgentTemplateInstallRepository;
use App\Domains\Nexus\Developer\Infrastructure\Repositories\EloquentApiKeyRepository;
use App\Domains\Nexus\Developer\Infrastructure\Repositories\EloquentIntegrationConnectionRepository;
use App\Domains\Nexus\Developer\Infrastructure\Repositories\EloquentWebhookDeliveryLogRepository;
use App\Domains\Nexus\Developer\Infrastructure\Repositories\EloquentWebhookSubscriptionRepository;
use App\Domains\Nexus\Credit\Application\Listeners\GrantStartingCreditsOnBusinessVerifiedListener;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentCreditBalanceRepository;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentCreditPurchaseSessionRepository;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentCreditTransactionRepository;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentHoldingCreditPoolRepository;
use App\Domains\Nexus\Credit\Infrastructure\Repositories\EloquentHoldingCreditPoolTransactionRepository;
use App\Domains\Nexus\Growth\Application\Actions\CancelCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\CloseCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\CreateCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\GetReferralStatusAction;
use App\Domains\Nexus\Growth\Application\Actions\JoinCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\LeaveCoalitionAction;
use App\Domains\Nexus\Growth\Application\Actions\ListOpenCoalitionsAction;
use App\Domains\Nexus\Growth\Application\Actions\SendAgentInviteAction;
use App\Domains\Nexus\Growth\Application\Listeners\CompleteCoalitionOnNegotiationAcceptedListener;
use App\Domains\Nexus\Growth\Application\Listeners\GrantReferralRewardOnBusinessVerifiedListener;
use App\Domains\Nexus\Growth\Application\Listeners\IssueReferralCodeOnBusinessVerifiedListener;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\InviteRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralSignupRepositoryInterface;
use App\Domains\Nexus\Growth\Infrastructure\Repositories\EloquentCoalitionMemberRepository;
use App\Domains\Nexus\Growth\Infrastructure\Repositories\EloquentCoalitionRepository;
use App\Domains\Nexus\Growth\Infrastructure\Repositories\EloquentInviteRepository;
use App\Domains\Nexus\Growth\Infrastructure\Repositories\EloquentReferralCodeRepository;
use App\Domains\Nexus\Growth\Infrastructure\Repositories\EloquentReferralSignupRepository;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use App\Domains\Nexus\Holding\Infrastructure\Repositories\EloquentHoldingRepository;
use App\Domains\Nexus\Holding\Infrastructure\Repositories\EloquentHoldingSubsidiaryRepository;
use App\Domains\Nexus\Llm\Application\Services\LLMProviderRegistry;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use App\Domains\Nexus\Llm\Infrastructure\Providers\AnthropicLLMProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\GroqLLMProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\LocalLlamaLLMProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\OpenAILLMProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\OpenRouterLLMProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\SelfHostedQwenLLMProvider;
use App\Domains\Nexus\Llm\Infrastructure\Repositories\EloquentLLMUsageLogRepository;
use App\Domains\Nexus\Marketplace\Application\Actions\GetBusinessNetworkAction;
use App\Domains\Nexus\Marketplace\Application\Actions\GetRecommendationsAction;
use App\Domains\Nexus\Marketplace\Application\Actions\RankSuppliersAction;
use App\Domains\Nexus\Marketplace\Application\Actions\RecommendAlternativeSuppliersAction;
use App\Domains\Nexus\Marketplace\Application\Actions\RecommendNegotiationTimingAction;
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
use App\Domains\Nexus\Negotiation\Application\Listeners\AutoRespondToNegotiationListener;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationMessageWasRecorded;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;
use App\Domains\Nexus\Negotiation\Infrastructure\Repositories\EloquentNegotiationMessageRepository;
use App\Domains\Nexus\Negotiation\Infrastructure\Repositories\EloquentNegotiationRepository;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\AddListingAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\SearchPrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceListingRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Repositories\EloquentPrivateMarketplaceListingRepository;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Repositories\EloquentPrivateMarketplaceMemberRepository;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Repositories\EloquentPrivateMarketplaceRepository;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;
use App\Domains\Nexus\Reputation\Application\Actions\ListReviewsForBusinessAction;
use App\Domains\Nexus\Reputation\Application\Actions\SubmitReviewAction;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use App\Domains\Nexus\Reputation\Infrastructure\Repositories\EloquentReviewRepository;
use App\Domains\Nexus\Sso\Application\Services\SsoProviderRegistry;
use App\Domains\Nexus\Sso\Infrastructure\Providers\GoogleSsoProvider;
use App\Domains\Nexus\Sso\Infrastructure\Providers\LdapSsoProvider;
use App\Domains\Nexus\Sso\Infrastructure\Providers\SamlSsoProvider;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Application\Services\StripeConfig;
use App\Modules\Commerce\Application\Services\StripePaymentGateway;
use App\Modules\Commerce\Application\Services\ZibalConfig;
use App\Modules\Commerce\Application\Services\ZibalPaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(SuspensionRecordRepositoryInterface::class, EloquentSuspensionRecordRepository::class);
        $this->app->bind(SuspensionAppealRepositoryInterface::class, EloquentSuspensionAppealRepository::class);
        $this->app->bind(AgentRepositoryInterface::class, EloquentAgentRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, EloquentServiceRepository::class);
        $this->app->bind(NegotiationRepositoryInterface::class, EloquentNegotiationRepository::class);
        $this->app->bind(NegotiationMessageRepositoryInterface::class, EloquentNegotiationMessageRepository::class);
        $this->app->bind(ContractRepositoryInterface::class, EloquentContractRepository::class);
        $this->app->bind(EscrowRepositoryInterface::class, EloquentEscrowRepository::class);
        $this->app->bind(DisputeCaseRepositoryInterface::class, EloquentDisputeCaseRepository::class);
        $this->app->bind(CreditBalanceRepositoryInterface::class, EloquentCreditBalanceRepository::class);
        $this->app->bind(CreditTransactionRepositoryInterface::class, EloquentCreditTransactionRepository::class);
        $this->app->bind(CreditPurchaseSessionRepositoryInterface::class, EloquentCreditPurchaseSessionRepository::class);
        $this->app->bind(LLMUsageLogRepositoryInterface::class, EloquentLLMUsageLogRepository::class);
        $this->app->bind(ReferralCodeRepositoryInterface::class, EloquentReferralCodeRepository::class);
        $this->app->bind(ReferralSignupRepositoryInterface::class, EloquentReferralSignupRepository::class);
        $this->app->bind(InviteRepositoryInterface::class, EloquentInviteRepository::class);
        $this->app->bind(CoalitionRepositoryInterface::class, EloquentCoalitionRepository::class);
        $this->app->bind(CoalitionMemberRepositoryInterface::class, EloquentCoalitionMemberRepository::class);
        $this->app->bind(ReviewRepositoryInterface::class, EloquentReviewRepository::class);
        $this->app->bind(HoldingRepositoryInterface::class, EloquentHoldingRepository::class);
        $this->app->bind(HoldingSubsidiaryRepositoryInterface::class, EloquentHoldingSubsidiaryRepository::class);
        $this->app->bind(HoldingCreditPoolRepositoryInterface::class, EloquentHoldingCreditPoolRepository::class);
        $this->app->bind(HoldingCreditPoolTransactionRepositoryInterface::class, EloquentHoldingCreditPoolTransactionRepository::class);
        $this->app->bind(ApprovalPolicyRepositoryInterface::class, EloquentApprovalPolicyRepository::class);
        $this->app->bind(ApprovalRequestRepositoryInterface::class, EloquentApprovalRequestRepository::class);
        $this->app->bind(ApprovalDecisionRepositoryInterface::class, EloquentApprovalDecisionRepository::class);
        $this->app->bind(PrivateMarketplaceRepositoryInterface::class, EloquentPrivateMarketplaceRepository::class);
        $this->app->bind(PrivateMarketplaceMemberRepositoryInterface::class, EloquentPrivateMarketplaceMemberRepository::class);
        $this->app->bind(PrivateMarketplaceListingRepositoryInterface::class, EloquentPrivateMarketplaceListingRepository::class);
        $this->app->bind(AuditLogEntryRepositoryInterface::class, EloquentAuditLogEntryRepository::class);
        $this->app->bind(AutomationRuleRepositoryInterface::class, EloquentAutomationRuleRepository::class);
        $this->app->bind(AutomationRunLogRepositoryInterface::class, EloquentAutomationRunLogRepository::class);
        $this->app->bind(ApiKeyRepositoryInterface::class, EloquentApiKeyRepository::class);
        $this->app->bind(WebhookSubscriptionRepositoryInterface::class, EloquentWebhookSubscriptionRepository::class);
        $this->app->bind(WebhookDeliveryLogRepositoryInterface::class, EloquentWebhookDeliveryLogRepository::class);
        $this->app->bind(IntegrationConnectionRepositoryInterface::class, EloquentIntegrationConnectionRepository::class);
        $this->app->bind(AgentStrategyTemplateRepositoryInterface::class, EloquentAgentStrategyTemplateRepository::class);
        $this->app->bind(AgentTemplateInstallRepositoryInterface::class, EloquentAgentTemplateInstallRepository::class);

        // Nexus's own PaymentGatewayRegistry singleton — CommerceServiceProvider
        // (where these adapter classes originally live) is disabled since
        // Nexus Phase 0, so its own registry is never booted; Nexus
        // registers the same adapter classes under its own instance
        // instead of reimplementing the Zibal/Stripe HTTP integration.
        $this->app->singleton(PaymentGatewayRegistry::class);

        // Phase 4 — LLM Provider System. Same Connector Pattern as
        // PaymentGatewayRegistry above, populated in boot() below.
        $this->app->singleton(LLMProviderRegistry::class);

        // Phase 7/M6 — SSO. Same Connector Pattern, populated in boot()
        // below (M6 registers 'google' for real; M8 adds 'saml'/'ldap'
        // stubs to this same instance).
        $this->app->singleton(SsoProviderRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/nexus/web.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/api.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/mcp.php'));

        $this->loadViewsFrom(resource_path('views/nexus'), 'nexus');
        $this->loadMigrationsFrom(database_path('migrations/nexus'));

        // Phase 9/M2 — Public REST API. Keyed by the authenticated ApiKey's
        // id (set on $request->attributes by EnsureValidApiKey, which always
        // runs before 'throttle:nexus-api' in routes/nexus/api.php), falling
        // back to the caller's IP only for the pre-authentication case
        // (a missing/invalid key never reaches here anyway, since
        // EnsureValidApiKey returns 401 before $next() — this fallback only
        // matters if 'throttle:nexus-api' is ever placed before it by
        // mistake, not a path any current route actually takes).
        RateLimiter::for('nexus-api', function (Request $request) {
            $keyId = $request->attributes->get('nexus_api_key')?->id();

            return Limit::perMinute(config('nexus.platform.api.rate_limit_per_minute'))
                ->by($keyId !== null ? "api-key:{$keyId}" : $request->ip());
        });

        Event::listen(BusinessWasVerified::class, CreateAgentOnBusinessVerifiedListener::class);
        Event::listen(BusinessWasVerified::class, GrantStartingCreditsOnBusinessVerifiedListener::class);
        Event::listen(BusinessWasVerified::class, IssueReferralCodeOnBusinessVerifiedListener::class);
        Event::listen(BusinessWasVerified::class, GrantReferralRewardOnBusinessVerifiedListener::class);
        Event::listen(NegotiationMessageWasRecorded::class, AutoRespondToNegotiationListener::class);
        Event::listen(NegotiationWasAccepted::class, GenerateContractOnNegotiationAcceptedListener::class);
        Event::listen(NegotiationWasAccepted::class, CompleteCoalitionOnNegotiationAcceptedListener::class);
        Event::listen(ContractWasGenerated::class, HoldEscrowOnContractGeneratedListener::class);
        Event::listen(EscrowWasDisputed::class, OpenDisputeCaseOnEscrowDisputedListener::class);

        // Phase 9/M3 — Webhooks. Additional listeners on the same three
        // events above (and NegotiationWasAccepted); each is a no-op for
        // any Business with no matching active subscription.
        Event::listen(NegotiationWasAccepted::class, DispatchWebhookOnNegotiationAcceptedListener::class);
        Event::listen(EscrowWasReleased::class, DispatchWebhookOnEscrowReleasedListener::class);
        Event::listen(ContractWasGenerated::class, DispatchWebhookOnContractGeneratedListener::class);

        // Real Payment Gateways (Phase 3/M3) — same Connector Pattern
        // CommerceServiceProvider's own (dead, since Commerce is disabled)
        // wiring already established; only 'zibal' is actually reachable
        // from PurchaseCreditsAction today (Toman-priced packages), but
        // 'stripe' is registered too so the connector wiring itself is
        // proven for a future non-IRT package set.
        $gateways = $this->app->make(PaymentGatewayRegistry::class);
        $gateways->register('zibal', new ZibalPaymentGateway(ZibalConfig::fromConfig()));
        $gateways->register('stripe', new StripePaymentGateway(StripeConfig::fromConfig()));

        $this->registerLlmProviders();
        $this->registerMcpCapabilityHandlers();

        // Phase 7/M6 — the one real, live SSO adapter this phase wires
        // end-to-end (see GoogleSsoProvider's own docblock for why only
        // Google, same "prove it with one real implementation" restraint
        // Zibal-only had in Phase 3/M3).
        $ssoProviders = $this->app->make(SsoProviderRegistry::class);
        $ssoProviders->register('google', new GoogleSsoProvider());

        // Phase 7/M8 — real classes, honestly stubbed (see their own
        // docblocks): registered so the admin surface
        // (NexusSsoProvidersController) can show them as "not configured"
        // rather than pretending they don't exist.
        $samlConfig = config('nexus.platform.sso.saml');
        $ssoProviders->register('saml', new SamlSsoProvider($samlConfig['entity_id'], $samlConfig['sso_url'], $samlConfig['certificate']));

        $ldapConfig = config('nexus.platform.sso.ldap');
        $ssoProviders->register('ldap', new LdapSsoProvider($ldapConfig['host'], $ldapConfig['base_dn']));
    }

    /**
     * Phase 4 — one entry per config('nexus.platform.llm.providers.*') key,
     * same "register the real adapter classes explicitly" shape the
     * payment gateway block above already uses (no config-loop reflection).
     */
    private function registerLlmProviders(): void
    {
        $providers = $this->app->make(LLMProviderRegistry::class);
        $config = config('nexus.platform.llm.providers');

        $providers->register('openai', new OpenAILLMProvider(
            $config['openai']['api_key'] ?? '',
            $config['openai']['model'],
            $config['openai']['base_url'],
        ));
        $providers->register('claude', new AnthropicLLMProvider(
            $config['claude']['api_key'] ?? '',
            $config['claude']['model'],
            $config['claude']['base_url'],
        ));
        $providers->register('openrouter', new OpenRouterLLMProvider(
            $config['openrouter']['api_key'] ?? '',
            $config['openrouter']['model'],
            $config['openrouter']['base_url'],
        ));
        $providers->register('groq', new GroqLLMProvider(
            $config['groq']['api_key'] ?? '',
            $config['groq']['model'],
            $config['groq']['base_url'],
        ));
        $providers->register('qwen-14b-local', new SelfHostedQwenLLMProvider(
            $config['qwen-14b-local']['api_key'] ?? '',
            $config['qwen-14b-local']['model'],
            $config['qwen-14b-local']['base_url'],
        ));
        $providers->register('llama-3.2-3b-local', new LocalLlamaLLMProvider(
            $config['llama-3.2-3b-local']['api_key'] ?? '',
            $config['llama-3.2-3b-local']['model'],
            $config['llama-3.2-3b-local']['base_url'],
        ));
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
        // Phase 7/M9 — every Nexus capability handler registered below
        // (directly or via the sub-methods) is transparently wrapped with
        // a hash-chained audit entry by this decorator; see its own
        // docblock for why this is the one place that needed to change.
        $handlers = new AuditingCapabilityHandlerRegistry(
            $this->app->make(CapabilityHandlerRegistry::class),
            $this->app,
        );

        $handlers->register('nexus.marketplace.search', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(SearchMarketplaceAction::class)->execute(
                callingBusinessId: $callingBusinessId,
                query: $input['query'] ?? null,
                industry: $input['industry'] ?? null,
            );
        });

        $handlers->register('nexus.marketplace.network', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(GetBusinessNetworkAction::class)->execute($callingBusinessId)->toArray();
        });

        $handlers->register('nexus.marketplace.recommendations', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(GetRecommendationsAction::class)->execute($callingBusinessId);
        });

        $handlers->register('nexus.marketplace.rank_suppliers', function (array $input, AuthContext $context) {
            $this->resolveActingBusiness($context);

            return $this->app->make(RankSuppliersAction::class)->execute(array_map('intval', $input['business_ids']));
        });

        $handlers->register('nexus.marketplace.alternatives', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(RecommendAlternativeSuppliersAction::class)->execute($callingBusinessId, (int) $input['target_business_id']);
        });

        $handlers->register('nexus.marketplace.negotiation_timing', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(RecommendNegotiationTimingAction::class)->execute($callingBusinessId, (int) $input['counterparty_business_id']);
        });

        $handlers->register('nexus.credit.balance', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $balance = $this->app->make(GetCreditBalanceAction::class)->execute($callingBusinessId);

            return $balance->toArray();
        });

        $handlers->register('nexus.referral.status', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(GetReferralStatusAction::class)->execute($callingBusinessId)->toArray();
        });

        $handlers->register('nexus.invite.send', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $invite = $this->app->make(SendAgentInviteAction::class)->execute(
                inviterBusinessId: $callingBusinessId,
                inviteeName: $input['invitee_name'],
                inviteeEmail: $input['invitee_email'],
                messageVariant: $input['message_variant'] ?? 'a',
            );

            return $invite->toArray();
        });

        $handlers->register('nexus.analytics.business', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(GetBusinessAnalyticsAction::class)->execute($callingBusinessId);
        });

        $handlers->register('nexus.analytics.market', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(GetMarketIntelligenceAction::class)->execute($callingBusinessId, $input['industry'] ?? null);
        });

        $handlers->register('nexus.analytics.forecast', function (array $input, AuthContext $context) {
            $this->resolveActingBusiness($context);

            return $this->app->make(ForecastSupplierReliabilityAction::class)->execute((int) $input['business_id']);
        });

        $handlers->register('nexus.analytics.risk', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(AssessDealRiskAction::class)->execute(
                $callingBusinessId,
                (int) $input['counterparty_business_id'],
                (int) $input['deal_amount'],
                $input['currency'],
            );
        });

        $handlers->register('nexus.analytics.scenario', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(SimulateNegotiationScenarioAction::class)->execute(
                $callingBusinessId,
                (int) $input['counterparty_business_id'],
                CatalogItemType::from($input['catalog_item_type']),
                (int) $input['hypothetical_unit_amount'],
            );
        });

        $this->registerCoalitionCapabilityHandlers($handlers);
        $this->registerNegotiationCapabilityHandlers($handlers);
        $this->registerReputationCapabilityHandlers($handlers);
        $this->registerPrivateMarketplaceCapabilityHandlers($handlers);
        $this->registerAutomationCapabilityHandlers($handlers);
    }

    private function registerAutomationCapabilityHandlers(AuditingCapabilityHandlerRegistry $handlers): void
    {
        $handlers->register('nexus.automation.create_recurring_order', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $rule = $this->app->make(CreateRecurringOrderRuleAction::class)->execute(
                businessId: $callingBusinessId,
                counterpartyBusinessId: (int) $input['counterparty_business_id'],
                catalogItemType: CatalogItemType::from($input['catalog_item_type']),
                catalogItemId: (int) $input['catalog_item_id'],
                priceAmount: (int) $input['price_amount'],
                priceCurrency: $input['price_currency'],
                quantity: (int) $input['quantity'],
                intervalDays: (int) $input['interval_days'],
            );

            return ['rule' => $rule->toArray()];
        });

        $handlers->register('nexus.automation.create_inventory_alert', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $rule = $this->app->make(CreateInventoryAlertRuleAction::class)->execute(
                $callingBusinessId,
                (int) $input['product_id'],
                (int) $input['threshold_quantity'],
            );

            return ['rule' => $rule->toArray()];
        });

        $handlers->register('nexus.automation.create_price_alert', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $rule = $this->app->make(CreatePriceAlertRuleAction::class)->execute(
                businessId: $callingBusinessId,
                catalogItemType: CatalogItemType::from($input['catalog_item_type']),
                catalogItemId: (int) $input['catalog_item_id'],
                targetPriceAmount: (int) $input['target_price_amount'],
                direction: PriceAlertDirection::from($input['direction']),
            );

            return ['rule' => $rule->toArray()];
        });

        $handlers->register('nexus.automation.list', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $rules = $this->app->make(ListAutomationRulesAction::class)->execute($callingBusinessId);

            return ['rules' => array_map(fn ($r) => $r->toArray(), $rules)];
        });

        $handlers->register('nexus.automation.pause', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $rule = $this->app->make(PauseAutomationRuleAction::class)->execute((int) $input['rule_id'], $callingBusinessId);

            return ['rule' => $rule->toArray()];
        });

        $handlers->register('nexus.automation.resume', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $rule = $this->app->make(ResumeAutomationRuleAction::class)->execute((int) $input['rule_id'], $callingBusinessId);

            return ['rule' => $rule->toArray()];
        });

        $handlers->register('nexus.automation.delete', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $this->app->make(DeleteAutomationRuleAction::class)->execute((int) $input['rule_id'], $callingBusinessId);

            return [];
        });
    }

    private function registerPrivateMarketplaceCapabilityHandlers(AuditingCapabilityHandlerRegistry $handlers): void
    {
        $handlers->register('nexus.private_marketplace.search', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            return $this->app->make(SearchPrivateMarketplaceAction::class)->execute(
                (int) $input['marketplace_id'],
                $callingBusinessId,
            );
        });

        $handlers->register('nexus.private_marketplace.list_listing', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $listing = $this->app->make(AddListingAction::class)->execute(
                marketplaceId: (int) $input['marketplace_id'],
                listingBusinessId: $callingBusinessId,
                catalogItemType: CatalogItemType::from($input['catalog_item_type']),
                catalogItemId: (int) $input['catalog_item_id'],
                specialPriceAmount: (int) $input['special_price_amount'],
                specialPriceCurrency: $input['special_price_currency'],
            );

            return ['listing' => $listing->toArray()];
        });
    }

    private function registerReputationCapabilityHandlers(AuditingCapabilityHandlerRegistry $handlers): void
    {
        $handlers->register('nexus.review.submit', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $review = $this->app->make(SubmitReviewAction::class)->execute(
                negotiationId: (int) $input['negotiation_id'],
                reviewerBusinessId: $callingBusinessId,
                rating: (int) $input['rating'],
                comment: $input['comment'] ?? null,
            );

            return ['review' => $review->toArray()];
        });

        $handlers->register('nexus.review.list', function (array $input, AuthContext $context) {
            $this->resolveActingBusiness($context);

            $reviews = $this->app->make(ListReviewsForBusinessAction::class)->execute((int) $input['business_id']);

            return ['reviews' => array_map(fn ($r) => $r->toArray(), $reviews)];
        });

        $handlers->register('nexus.reputation.score', function (array $input, AuthContext $context) {
            $this->resolveActingBusiness($context);

            $score = $this->app->make(CalculateReputationScoreAction::class)->execute((int) $input['business_id']);

            return ['score' => $score->toArray()];
        });
    }

    private function registerCoalitionCapabilityHandlers(AuditingCapabilityHandlerRegistry $handlers): void
    {
        $handlers->register('nexus.coalition.create', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $coalition = $this->app->make(CreateCoalitionAction::class)->execute(
                organizerBusinessId: $callingBusinessId,
                targetBusinessId: (int) $input['target_business_id'],
                catalogItemType: CatalogItemType::from($input['catalog_item_type']),
                catalogItemId: (int) $input['catalog_item_id'],
                unitPriceAmount: (int) $input['unit_price_amount'],
                unitPriceCurrency: $input['unit_price_currency'],
                minParticipants: (int) $input['min_participants'],
                discountPercent: (float) $input['discount_percent'],
                organizerQuantity: (int) $input['quantity'],
            );

            return ['coalition' => $coalition->toArray()];
        });

        $handlers->register('nexus.coalition.join', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $coalition = $this->app->make(JoinCoalitionAction::class)->execute(
                (int) $input['coalition_id'],
                $callingBusinessId,
                (int) $input['quantity'],
            );

            return ['coalition' => $coalition->toArray()];
        });

        $handlers->register('nexus.coalition.list', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $coalitions = $this->app->make(ListOpenCoalitionsAction::class)->execute($callingBusinessId);

            return ['coalitions' => array_map(fn ($c) => $c->toArray(), $coalitions)];
        });

        $handlers->register('nexus.coalition.close', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $coalition = $this->app->make(CloseCoalitionAction::class)->execute((int) $input['coalition_id'], $callingBusinessId);

            return ['coalition' => $coalition->toArray()];
        });

        $handlers->register('nexus.coalition.leave', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $this->app->make(LeaveCoalitionAction::class)->execute((int) $input['coalition_id'], $callingBusinessId);

            return [];
        });

        $handlers->register('nexus.coalition.cancel', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->resolveActingBusiness($context);

            $coalition = $this->app->make(CancelCoalitionAction::class)->execute((int) $input['coalition_id'], $callingBusinessId);

            return ['coalition' => $coalition->toArray()];
        });
    }

    private function registerNegotiationCapabilityHandlers(AuditingCapabilityHandlerRegistry $handlers): void
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
