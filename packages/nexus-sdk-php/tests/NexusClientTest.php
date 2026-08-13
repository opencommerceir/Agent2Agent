<?php

declare(strict_types=1);

namespace Nexus\Sdk\Tests;

use Nexus\Sdk\NexusApiException;
use Nexus\Sdk\NexusClient;
use PHPUnit\Framework\TestCase;

final class NexusClientTest extends TestCase
{
    public function test_getCreditBalance_returnsDataPayload(): void
    {
        $client = new NexusClient('https://nexus.example.com', 'nx_test', function (string $method, string $path, array $options) {
            $this->assertSame('GET', $method);
            $this->assertSame('/nexus/api/v1/credit/balance', $path);

            return ['status' => 200, 'body' => json_encode(['data' => ['businessId' => 1, 'balance' => 500]])];
        });

        $result = $client->getCreditBalance();

        $this->assertSame(500, $result['balance']);
    }

    public function test_getCatalog_passesQueryParameter(): void
    {
        $captured = null;
        $client = new NexusClient('https://nexus.example.com', 'nx_test', function (string $method, string $path, array $options) use (&$captured) {
            $captured = $options['query'] ?? [];

            return ['status' => 200, 'body' => json_encode(['data' => ['products' => [], 'services' => []]])];
        });

        $client->getCatalog('widget');

        $this->assertSame(['query' => 'widget'], $captured);
    }

    public function test_errorResponse_throwsNexusApiExceptionWithEnvelopeDetails(): void
    {
        $client = new NexusClient('https://nexus.example.com', 'nx_test', fn () => [
            'status' => 403,
            'body' => json_encode(['error' => ['code' => 'FORBIDDEN', 'message' => "Missing scope: catalog.read"]]),
        ]);

        try {
            $client->getCatalog();
            $this->fail('Expected NexusApiException.');
        } catch (NexusApiException $e) {
            $this->assertSame(403, $e->httpStatus);
            $this->assertSame('FORBIDDEN', $e->errorCode);
            $this->assertSame('Missing scope: catalog.read', $e->getMessage());
        }
    }

    public function test_graphql_sendsQueryAndVariablesAsJsonBody(): void
    {
        $captured = null;
        $client = new NexusClient('https://nexus.example.com', 'nx_test', function (string $method, string $path, array $options) use (&$captured) {
            $captured = $options['json'] ?? null;

            return ['status' => 200, 'body' => json_encode(['data' => ['creditBalance' => ['balance' => 10]]])];
        });

        $client->graphql('{ creditBalance { balance } }', ['foo' => 'bar']);

        $this->assertSame('{ creditBalance { balance } }', $captured['query']);
        $this->assertSame(['foo' => 'bar'], $captured['variables']);
    }

    public function test_verifyWebhookSignature_validSignature_returnsTrue(): void
    {
        $body = '{"event":"negotiation.accepted"}';
        $secret = 'shhh';
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $this->assertTrue(NexusClient::verifyWebhookSignature($body, $signature, $secret));
    }

    public function test_verifyWebhookSignature_tamperedBody_returnsFalse(): void
    {
        $secret = 'shhh';
        $signature = 'sha256='.hash_hmac('sha256', '{"event":"original"}', $secret);

        $this->assertFalse(NexusClient::verifyWebhookSignature('{"event":"tampered"}', $signature, $secret));
    }
}
