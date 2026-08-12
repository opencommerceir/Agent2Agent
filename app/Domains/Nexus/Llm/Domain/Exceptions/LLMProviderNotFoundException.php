<?php

namespace App\Domains\Nexus\Llm\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Thrown when `LLMProviderRegistry::get()` is asked for an unregistered
 * provider id — mirrors `PaymentGatewayNotFoundException` exactly (same
 * Connector-Registry-miss situation, same 404 mapping).
 */
final class LLMProviderNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
