<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\Exceptions\WooCommerceApiException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * The real WooCommerceClientInterface implementation: talks to a live
 * WooCommerce store's REST API using its Consumer Key/Secret (WooCommerce's
 * own auth scheme — query-string credentials over HTTPS, not OAuth). Every
 * failure mode (network error, non-2xx response, malformed JSON body) is
 * normalized into WooCommerceApiException so callers never need to know
 * Guzzle exists.
 */
final class WooCommerceClient implements WooCommerceClientInterface
{
    private const PRODUCTS_PATH = '/wp-json/wc/v3/products';

    private readonly ClientInterface $http;

    public function __construct(
        private readonly WooCommerceConfig $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => $this->config->storeUrl,
            'timeout' => $this->config->timeoutSeconds,
        ]);
    }

    public function getProducts(int $page = 1, int $perPage = 20): array
    {
        $decoded = $this->decode($this->request(self::PRODUCTS_PATH, [
            'page' => $page,
            'per_page' => $perPage,
        ]));

        return is_array($decoded) ? $decoded : [];
    }

    public function getProduct(string $externalId): ?array
    {
        try {
            $response = $this->http->request('GET', self::PRODUCTS_PATH."/{$externalId}", [
                'query' => $this->authQuery(),
            ]);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }

            throw new WooCommerceApiException("WooCommerce API request failed: {$e->getMessage()}", previous: $e);
        } catch (GuzzleException $e) {
            throw new WooCommerceApiException("WooCommerce API request failed: {$e->getMessage()}", previous: $e);
        }

        $decoded = $this->decode($response);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function request(string $path, array $query): ResponseInterface
    {
        try {
            return $this->http->request('GET', $path, [
                'query' => array_merge($this->authQuery(), $query),
            ]);
        } catch (GuzzleException $e) {
            throw new WooCommerceApiException("WooCommerce API request failed: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @return array<string, string>
     */
    private function authQuery(): array
    {
        return [
            'consumer_key' => $this->config->consumerKey,
            'consumer_secret' => $this->config->consumerSecret,
        ];
    }

    private function decode(ResponseInterface $response): mixed
    {
        $decoded = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WooCommerceApiException('WooCommerce API returned a malformed (non-JSON) response.');
        }

        return $decoded;
    }
}
