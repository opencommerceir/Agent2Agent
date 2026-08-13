<?php

namespace App\Domains\Nexus\Developer\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by SyncCatalogToIntegrationAction when the outbound HTTP request
 * to a Business's own configured target fails — unlike
 * DispatchWebhookEventAction (which never throws, since a webhook
 * receiver being down must not break a Negotiation/Escrow/Contract flow),
 * a sync is a direct, on-demand action the Business itself just clicked,
 * so surfacing the failure back to them immediately is the right
 * behavior here, not silently logging it.
 */
final class IntegrationSyncFailedException extends RuntimeException
{
}
