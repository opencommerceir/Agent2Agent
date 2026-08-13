<?php

namespace App\Domains\Nexus\Developer\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Developer\Application\Actions\CreateWebhookSubscriptionAction;
use App\Domains\Nexus\Developer\Application\Actions\ListWebhookDeliveriesAction;
use App\Domains\Nexus\Developer\Application\Actions\ListWebhookSubscriptionsAction;
use App\Domains\Nexus\Developer\Application\Actions\RevokeWebhookSubscriptionAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal webhook subscription management (Phase 9/M3) — same
 * single-page shape ApiKeyController (M1) already established. The
 * signing secret is flashed once after `store()`, exactly like ApiKey's
 * plaintext key.
 */
class WebhookSubscriptionController extends Controller
{
    public function __construct(
        private readonly ListWebhookSubscriptionsAction $listSubscriptions,
        private readonly CreateWebhookSubscriptionAction $createSubscription,
        private readonly RevokeWebhookSubscriptionAction $revokeSubscription,
        private readonly ListWebhookDeliveriesAction $listDeliveries,
    ) {
    }

    public function index(): View
    {
        $businessId = $this->actingBusinessId();

        return view('nexus::developer.webhooks.index', [
            'subscriptions' => $this->listSubscriptions->execute($businessId),
            'deliveries' => $this->listDeliveries->execute($businessId),
            'events' => WebhookEvent::cases(),
            'plainSecret' => session('plain_webhook_secret'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', array_map(fn (WebhookEvent $event) => $event->value, WebhookEvent::cases()))],
        ]);

        $result = $this->createSubscription->execute(
            businessId: $this->actingBusinessId(),
            url: $validated['url'],
            events: array_map(fn (string $value) => WebhookEvent::from($value), $validated['events']),
        );

        return redirect()->route('nexus.developer.webhooks.index')
            ->with('plain_webhook_secret', $result['secret']);
    }

    public function revoke(int $subscription): RedirectResponse
    {
        $this->revokeSubscription->execute($subscription, $this->actingBusinessId());

        return redirect()->route('nexus.developer.webhooks.index');
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}
