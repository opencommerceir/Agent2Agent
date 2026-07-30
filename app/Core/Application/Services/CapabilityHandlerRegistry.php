<?php

namespace App\Core\Application\Services;

use App\Core\Domain\Exceptions\CapabilityNotFoundException;

/**
 * Maps a capability name to the callable that actually executes it.
 *
 * This resolves a gap flagged in the Phase 1 review: "no mechanism
 * decided yet for wiring a capability to a real handler." Registering a
 * capability (RegisterCapabilityAction) only ever created a description
 * — MCP itself must never contain execution logic (Decision 007), so a
 * Domain Module registers its own handlers here (see
 * DemoServiceProvider for the pattern) and CapabilityExecutionService
 * looks them up by name at request time.
 *
 * A capability existing in the Capability Registry with no handler
 * registered here is a valid, meaningful state — it means "described but
 * not yet wired to an implementation" — and getHandler() reports that as
 * CapabilityNotFoundException (the same exception GetCapabilityAction
 * throws for a capability that isn't registered at all), since from an
 * Agent's point of view both cases are indistinguishable: "there is
 * nothing here to execute."
 *
 * Handlers receive the authenticated Agent's tenantId as a second
 * argument (Phase 2 decision): Commerce data is tenant-scoped, unlike
 * Demo's stateless capabilities, and CapabilityExecutionService has no
 * other way to tell a handler which tenant's data to touch. Passed as an
 * explicit parameter, not resolved from a request-scoped container
 * binding, to keep the dependency visible in every handler's signature
 * (Explicit Over Magic) rather than hidden global state.
 */
final class CapabilityHandlerRegistry
{
    /**
     * @var array<string, callable>
     */
    private array $handlers = [];

    /**
     * @param callable(array<string, mixed>, int): array<string, mixed> $handler
     */
    public function register(string $capabilityName, callable $handler): void
    {
        $this->handlers[$capabilityName] = $handler;
    }

    public function hasHandler(string $capabilityName): bool
    {
        return isset($this->handlers[$capabilityName]);
    }

    /**
     * @return callable(array<string, mixed>, int): array<string, mixed>
     */
    public function getHandler(string $capabilityName): callable
    {
        if (! isset($this->handlers[$capabilityName])) {
            throw new CapabilityNotFoundException(
                "No execution handler registered for capability [{$capabilityName}]."
            );
        }

        return $this->handlers[$capabilityName];
    }
}
