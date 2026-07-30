<?php

namespace OpenCommerce\SDK\Exceptions;

/**
 * Thrown for HTTP 404 — the requested capability does not exist on the
 * platform (also thrown client-side by MCPClient::getCapability() when a
 * name isn't found in the discovery list).
 */
final class NotFoundException extends MCPException
{
}
