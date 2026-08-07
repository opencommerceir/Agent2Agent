<?php

namespace App\Modules\Commerce\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\Actions\ConfirmRedirectPaymentAction;
use App\Modules\Commerce\Application\Services\StripeWebhookVerifier;
use App\Modules\Commerce\Domain\Repositories\PaymentSessionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `POST /payments/stripe/webhook` (§7.37) — Stripe's own real
 * confirmation mechanism (the browser `success_url` redirect,
 * `PaymentCallbackController`, is UX only; this route is authoritative).
 *
 * Verifies the raw request body against `Stripe-Signature` manually
 * (`StripeWebhookVerifier`, no `stripe-php` SDK dependency, §7.37's own
 * docblock) — `$request->getContent()` is the **raw**, unparsed body;
 * Laravel's own request pipeline doesn't rewrite it, but this Controller
 * deliberately never touches `$request->json()`/`$request->input()`
 * either, to make that guarantee obvious from reading this class alone.
 *
 * Per Stripe's own documented best practice: always return a fast `2xx`
 * once the signature itself is valid, even if downstream processing
 * fails — a `4xx`/`5xx` here only triggers a pointless retry storm for a
 * problem a retry can't fix (this codebase's own bug, not a transient
 * one). Only a bad signature is a real `400`.
 */
final class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeWebhookVerifier $verifier,
        private readonly PaymentSessionRepositoryInterface $sessions,
        private readonly ConfirmRedirectPaymentAction $confirm,
    ) {
    }

    public function handle(Request $request): Response
    {
        $secret = (string) config('payment_gateways.stripe.webhook_secret', '');
        $signatureHeader = (string) $request->header('Stripe-Signature', '');
        $rawPayload = $request->getContent();

        if ($secret === '' || ! $this->verifier->verify($rawPayload, $signatureHeader, $secret)) {
            return response('Invalid signature.', 400);
        }

        $event = json_decode($rawPayload, true);

        if (! is_array($event) || ($event['type'] ?? null) !== 'checkout.session.completed') {
            return response('', 200);
        }

        $providerReference = $event['data']['object']['id'] ?? null;

        if (! is_string($providerReference)) {
            return response('', 200);
        }

        $session = $this->sessions->findByProviderReference('stripe', $providerReference);

        if ($session === null || $session->id() === null) {
            Log::warning('Stripe webhook for an unknown PaymentSession', ['provider_reference' => $providerReference]);

            return response('', 200);
        }

        try {
            $this->confirm->execute($session->id());
        } catch (Throwable $e) {
            Log::error('Stripe webhook confirmation failed', [
                'session_id' => $session->id(),
                'error' => $e->getMessage(),
            ]);
        }

        return response('', 200);
    }
}
