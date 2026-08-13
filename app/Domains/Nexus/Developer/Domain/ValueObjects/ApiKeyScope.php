<?php

namespace App\Domains\Nexus\Developer\Domain\ValueObjects;

/**
 * A closed set of read/manage grants an ApiKey can be issued with — never
 * a free-form permission string, so the Public REST API (Phase 9/M2) can
 * exhaustively match on cases instead of trusting an arbitrary caller-
 * supplied scope name. Grows additively as new REST surfaces ship (Webhook
 * management arrives in Phase 9/M3) — the same append-only shape
 * NotificationType/CreditTransactionType already follow.
 */
enum ApiKeyScope: string
{
    case BusinessRead = 'business.read';
    case CatalogRead = 'catalog.read';
    case MarketplaceRead = 'marketplace.read';
    case NegotiationRead = 'negotiation.read';
    case CreditRead = 'credit.read';
}
