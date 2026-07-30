<?php

namespace App\Core\Domain\Exceptions\Contracts;

/**
 * Marker interface for "the referenced thing does not exist" exceptions,
 * implemented by any exception — Core's own or a Domain Module's — that
 * MCPExceptionHandler should map to the NOT_FOUND (404) envelope.
 *
 * Exists so MCPExceptionHandler (Core) never has to import a Domain
 * Module's exception classes directly: Core must never depend on
 * business domains (CLAUDE.md — "Core must not know about Products,
 * Orders, ..."). Core defines the contract; a Domain Module's exception
 * implements it — the dependency points the correct direction.
 */
interface NotFoundExceptionInterface
{
}
