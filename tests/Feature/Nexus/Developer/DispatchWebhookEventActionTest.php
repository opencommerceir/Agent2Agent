<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Developer\Application\Actions\CreateWebhookSubscriptionAction;
use App\Domains\Nexus\Developer\Application\Actions\DispatchWebhookEventAction;
use App\Domains\Nexus\Developer\Application\Actions\ListWebhookDeliveriesAction;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookDeliveryLogRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No live HTTP access assumed — every request is intercepted by a Guzzle
 * MockHandler, same discipline every external Connector's own test in this
 * codebase already uses (see GroqLLMProviderTest).
 */
class DispatchWebhookEventActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_onSuccess_signsPayloadAndRecordsSuccessfulDelivery(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $result = app(CreateWebhookSubscriptionAction::class)->execute($business->id, 'https://example.com/hook', [WebhookEvent::NegotiationAccepted]);

        $captured = null;
        $mock = new MockHandler([
            function (Psr7Request $request) use (&$captured) {
                $captured = $request;

                return new Response(200);
            },
        ]);
        $action = $this->action(new Client(['handler' => HandlerStack::create($mock)]));

        $action->execute($business->id, WebhookEvent::NegotiationAccepted, ['negotiationId' => 42]);

        $this->assertSame('negotiation.accepted', $captured->getHeaderLine('X-Nexus-Event'));
        $body = (string) $captured->getBody();
        $expectedSignature = 'sha256='.hash_hmac('sha256', $body, $result['secret']);
        $this->assertSame($expectedSignature, $captured->getHeaderLine('X-Nexus-Signature'));
        $this->assertSame('negotiation.accepted', json_decode($body, true)['event']);
        $this->assertSame(42, json_decode($body, true)['data']['negotiationId']);

        $deliveries = app(ListWebhookDeliveriesAction::class)->execute($business->id);
        $this->assertCount(1, $deliveries);
        $this->assertTrue($deliveries[0]->succeeded);
        $this->assertSame(200, $deliveries[0]->httpStatus);
    }

    public function test_execute_onFailure_recordsFailedDeliveryWithoutThrowing(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        app(CreateWebhookSubscriptionAction::class)->execute($business->id, 'https://example.com/hook', [WebhookEvent::EscrowReleased]);

        $mock = new MockHandler([
            new ConnectException('Connection refused', new Psr7Request('POST', 'https://example.com/hook')),
        ]);
        $action = $this->action(new Client(['handler' => HandlerStack::create($mock)]));

        $action->execute($business->id, WebhookEvent::EscrowReleased, ['escrowId' => 1]);

        $deliveries = app(ListWebhookDeliveriesAction::class)->execute($business->id);
        $this->assertCount(1, $deliveries);
        $this->assertFalse($deliveries[0]->succeeded);
        $this->assertNull($deliveries[0]->httpStatus);
        $this->assertNotNull($deliveries[0]->errorMessage);
    }

    public function test_execute_noMatchingSubscription_doesNothing(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        app(CreateWebhookSubscriptionAction::class)->execute($business->id, 'https://example.com/hook', [WebhookEvent::ContractGenerated]);

        $action = $this->action(new Client(['handler' => HandlerStack::create(new MockHandler([]))]));

        $action->execute($business->id, WebhookEvent::NegotiationAccepted, []);

        $this->assertCount(0, app(ListWebhookDeliveriesAction::class)->execute($business->id));
    }

    private function action(Client $http): DispatchWebhookEventAction
    {
        return new DispatchWebhookEventAction(
            app(WebhookSubscriptionRepositoryInterface::class),
            app(WebhookDeliveryLogRepositoryInterface::class),
            $http,
        );
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }
}
