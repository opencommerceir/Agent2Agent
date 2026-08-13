<?php

namespace App\Domains\Nexus\Developer\Interfaces\GraphQL;

use GraphQL\Type\Definition\CustomScalarType;

/**
 * A pragmatic scope decision (Phase 9/M5): every top-level Query field
 * below is a real, typed GraphQL field, but genuinely nested/heterogeneous
 * substructures (a Negotiation's currentTerms, a Business's agent/
 * reputationScore, a marketplace listing) are exposed as this JSON scalar
 * rather than individually modeled ObjectTypes. Fully modeling every DTO's
 * nested shape as GraphQL types is real future work, not something this
 * milestone skipped by accident — the REST API (M2) already exposes the
 * same data with full JSON structure for a client that needs to inspect
 * those fields; GraphQL's actual value here is in letting a caller choose
 * *which* top-level resources to fetch in one round trip, not sub-field
 * selection into every nested object.
 *
 * Output-only (no `parseValue`/`parseLiteral`) — this API is read-only,
 * so JSON never appears as an input argument type.
 */
final class JsonScalarType extends CustomScalarType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'JSON',
            'description' => 'Arbitrary JSON-serializable data — see this schema\'s own scope note on why some fields use this instead of a fully modeled type.',
            'serialize' => static fn (mixed $value): mixed => $value,
        ]);
    }
}
