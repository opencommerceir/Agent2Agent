<?php

namespace App\Domains\Nexus\Developer\Interfaces\GraphQL;

use App\Domains\Nexus\Agent\Application\DTOs\AgentData;
use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessDashboardAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Catalog\Application\Actions\SearchCatalogAction;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Developer\Domain\Entities\ApiKey;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Domains\Nexus\Negotiation\Application\Actions\GetNegotiationAction;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * Builds the read-only GraphQL schema (Phase 9/M5) — one Query field per
 * Public REST API endpoint (M2), each resolver calling the exact same
 * Action its REST controller counterpart already calls (Extend, Don't
 * Rebuild — no business logic duplicated for this third channel, same
 * restraint the REST controllers themselves already followed against the
 * MCP capability handlers). Scope enforcement happens per-field here
 * (requireScope()) rather than per-request the way EnsureValidApiKey does
 * for REST — GraphQL naturally supports partial results (a field a key
 * lacks scope for reports an error for just that field, not the whole
 * request), so field-level is the more idiomatic fit, not a workaround.
 */
final class PublicApiSchemaFactory
{
    public function __construct(
        private readonly GetBusinessDashboardAction $getBusinessDashboard,
        private readonly SearchCatalogAction $searchCatalog,
        private readonly SearchMarketplaceAction $searchMarketplace,
        private readonly GetNegotiationAction $getNegotiation,
        private readonly GetCreditBalanceAction $getCreditBalance,
    ) {
    }

    public function build(): Schema
    {
        $json = new JsonScalarType();

        $businessProfileType = new ObjectType([
            'name' => 'BusinessProfile',
            'fields' => [
                'business' => Type::nonNull($json),
                'agent' => $json,
                'productCount' => Type::nonNull(Type::int()),
                'serviceCount' => Type::nonNull(Type::int()),
                'creditBalance' => Type::int(),
                'activeNegotiations' => Type::nonNull(Type::int()),
                'reputationScore' => Type::nonNull($json),
            ],
        ]);

        $catalogType = new ObjectType([
            'name' => 'Catalog',
            'fields' => [
                'products' => Type::nonNull($json),
                'services' => Type::nonNull($json),
            ],
        ]);

        $marketplaceSearchType = new ObjectType([
            'name' => 'MarketplaceSearchResult',
            'fields' => [
                'listings' => Type::nonNull($json),
            ],
        ]);

        $negotiationType = new ObjectType([
            'name' => 'Negotiation',
            'fields' => [
                'id' => Type::int(),
                'initiatorBusinessId' => Type::nonNull(Type::int()),
                'counterpartyBusinessId' => Type::nonNull(Type::int()),
                'catalogItemType' => Type::nonNull(Type::string()),
                'catalogItemId' => Type::nonNull(Type::int()),
                'status' => Type::nonNull(Type::string()),
                'currentTerms' => Type::nonNull($json),
                'roundCount' => Type::nonNull(Type::int()),
                'maxRounds' => Type::nonNull(Type::int()),
                'rejectionReason' => Type::string(),
                'pendingApprovalBusinessId' => Type::int(),
            ],
        ]);

        $creditBalanceType = new ObjectType([
            'name' => 'CreditBalance',
            'fields' => [
                'businessId' => Type::nonNull(Type::int()),
                'balance' => Type::nonNull(Type::int()),
            ],
        ]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                // Deliberately nullable at the Query root, even though a
                // successful call always returns an object — GraphQL's own
                // null-propagation rule means a NonNull field's error
                // bubbles up and nulls out its nearest nullable ancestor;
                // for the *root* Query type that ancestor is `data` itself,
                // which would turn one field's scope error (a routine,
                // expected outcome — an API key can legitimately lack a
                // scope) into the whole response losing every other
                // field's data. Nullable root fields keep each field's
                // failure contained to itself, the same partial-result
                // guarantee the rest of this schema's own docblock
                // promises.
                'business' => [
                    'type' => $businessProfileType,
                    'resolve' => function (mixed $_, array $args, array $context) {
                        $this->requireScope($context, ApiKeyScope::BusinessRead);

                        $data = $this->getBusinessDashboard->execute($context['businessId']);

                        return [
                            'business' => BusinessData::fromEntity($data['business'])->toArray(),
                            'agent' => $data['agent'] ? AgentData::fromEntity($data['agent'])->toArray() : null,
                            'productCount' => $data['productCount'],
                            'serviceCount' => $data['serviceCount'],
                            'creditBalance' => $data['creditBalance'],
                            'activeNegotiations' => $data['activeNegotiations'],
                            'reputationScore' => $data['reputationScore']->toArray(),
                        ];
                    },
                ],
                'catalog' => [
                    'type' => $catalogType,
                    'args' => ['query' => ['type' => Type::string()]],
                    'resolve' => function (mixed $_, array $args, array $context) {
                        $this->requireScope($context, ApiKeyScope::CatalogRead);

                        $result = $this->searchCatalog->execute($context['businessId'], $args['query'] ?? '');

                        return [
                            'products' => array_map(fn ($product) => $product->toArray(), $result['products']),
                            'services' => array_map(fn ($service) => $service->toArray(), $result['services']),
                        ];
                    },
                ],
                'marketplaceSearch' => [
                    'type' => $marketplaceSearchType,
                    'args' => [
                        'query' => ['type' => Type::string()],
                        'industry' => ['type' => Type::string()],
                    ],
                    'resolve' => function (mixed $_, array $args, array $context) {
                        $this->requireScope($context, ApiKeyScope::MarketplaceRead);

                        return $this->searchMarketplace->execute($context['businessId'], $args['query'] ?? null, $args['industry'] ?? null);
                    },
                ],
                'negotiation' => [
                    'type' => $negotiationType,
                    'args' => ['id' => ['type' => Type::nonNull(Type::int())]],
                    'resolve' => function (mixed $_, array $args, array $context) {
                        $this->requireScope($context, ApiKeyScope::NegotiationRead);

                        try {
                            return $this->getNegotiation->execute((int) $args['id'], $context['businessId'])->toArray();
                        } catch (\InvalidArgumentException $e) {
                            throw new Error($e->getMessage(), previous: $e);
                        }
                    },
                ],
                'creditBalance' => [
                    'type' => $creditBalanceType,
                    'resolve' => function (mixed $_, array $args, array $context) {
                        $this->requireScope($context, ApiKeyScope::CreditRead);

                        return $this->getCreditBalance->execute($context['businessId'])->toArray();
                    },
                ],
            ],
        ]);

        return new Schema(['query' => $queryType]);
    }

    /**
     * @param array{businessId: int, apiKey: ApiKey} $context
     */
    private function requireScope(array $context, ApiKeyScope $scope): void
    {
        if (! $context['apiKey']->hasScope($scope)) {
            throw new Error("This API key is missing the required '{$scope->value}' scope.");
        }
    }
}
