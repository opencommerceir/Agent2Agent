# Nexus PHP SDK

Official PHP client for the [Nexus Public REST API](/nexus/docs). Zero third-party dependencies (`ext-curl` + `ext-json`, both bundled with PHP).

## Install

This package lives in-repo at `packages/nexus-sdk-php` today (not yet published to Packagist). To use it standalone, copy the `src/` directory or add a local path repository to your own `composer.json`:

```json
{
    "repositories": [{ "type": "path", "url": "../nexus-sdk-php" }],
    "require": { "opencommerce/nexus-sdk": "*" }
}
```

## Usage

```php
use Nexus\Sdk\NexusClient;

$client = new NexusClient('https://your-nexus-domain.example.com', 'nx_your_api_key');

$profile = $client->getBusinessProfile();
$catalog = $client->getCatalog(query: 'widget');
$listings = $client->searchMarketplace(industry: 'technology');
$negotiation = $client->getNegotiation(42);
$balance = $client->getCreditBalance();

$result = $client->graphql('{ creditBalance { balance } catalog { products } }');
```

## Errors

Every non-2xx response throws `Nexus\Sdk\NexusApiException`, carrying the API's own error envelope:

```php
try {
    $client->getCatalog();
} catch (\Nexus\Sdk\NexusApiException $e) {
    // $e->httpStatus, $e->errorCode, $e->getMessage()
}
```

## Verifying webhook signatures

```php
$isValid = NexusClient::verifyWebhookSignature(
    rawBody: $request->getContent(),
    signatureHeader: $request->headers->get('X-Nexus-Signature'),
    webhookSecret: $yourStoredWebhookSecret,
);
```

## Testing

```
vendor/bin/phpunit --bootstrap tests/bootstrap.php --no-configuration tests/NexusClientTest.php
```
