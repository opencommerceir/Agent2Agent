<?php

namespace App\Modules\Commerce\Domain\Connectors;

/**
 * Base contract every Commerce connector implements. A Connector's only
 * job is communication + translation into UCP (Connector Conventions) —
 * it must never contain business rules.
 */
interface ConnectorInterface
{
    /**
     * Identifies the connector for ConnectorRegistry lookups and for
     * stamping UCPProduct::$sourceSystem etc. (e.g. 'woocommerce', 'mock').
     */
    public function getName(): string;

    /**
     * Lightweight health check. Real connectors ping the external API;
     * this is the seam for the "health monitoring" responsibility
     * architecture.md assigns to the Connection Manager.
     */
    public function isConnected(): bool;
}
