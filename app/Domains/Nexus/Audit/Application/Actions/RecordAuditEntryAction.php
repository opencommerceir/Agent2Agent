<?php

namespace App\Domains\Nexus\Audit\Application\Actions;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Domains\Nexus\Agent\Application\Actions\ResolveActingBusinessAction;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;
use App\Domains\Nexus\Audit\Domain\ValueObjects\AuditOutcome;
use Throwable;

/**
 * The single point every Nexus MCP capability call passes through to get
 * a hash-chained audit entry (see
 * App\Domains\Nexus\Audit\Application\Services\AuditingCapabilityHandlerRegistry
 * for where this gets wired around every registered handler — one choke
 * point, not one edit per capability, the same lesson Phase 6/M4's
 * suspension check already established via ResolveActingBusinessAction).
 *
 * Looks up the calling Business directly via AgentRepositoryInterface
 * (bypassing the suspension check) purely to know *who* to attribute this
 * entry to, then separately calls ResolveActingBusinessAction for its
 * actual enforcement side effect (throwing PermissionDeniedException for
 * a suspended Business). Without this split, a suspended Business's
 * denied attempts would all log with businessId=null — exactly the
 * "who tried what while suspended" question a compliance audit trail
 * exists to answer, so recording it as unknown would defeat the point.
 * The handler's own internal resolveActingBusiness() call still happens
 * too on the success path — a second cheap indexed read, not a
 * correctness issue (same self-contained-check tolerance every Nexus
 * Action already has).
 *
 * `inputSummary` intentionally stores only the top-level input field
 * names (array_keys), never values — a compliance trail needs "what shape
 * of call happened", not a second copy of every negotiation price or
 * business email sitting in a table with no encryption-at-rest story.
 */
final class RecordAuditEntryAction
{
    public function __construct(
        private readonly AuditLogEntryRepositoryInterface $repository,
        private readonly ResolveActingBusinessAction $resolveActingBusiness,
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function wrap(string $capabilityName, AuthContext $context, array $input, callable $handler): mixed
    {
        $startedAt = microtime(true);
        $businessId = $this->agents->findByCoreAgentId($context->agentId)?->businessId();

        try {
            $this->resolveActingBusiness->execute($context->agentId);
        } catch (PermissionDeniedException $e) {
            $this->append($capabilityName, $businessId, $context->agentId, AuditOutcome::Denied, $input, $startedAt);
            throw $e;
        } catch (Throwable $e) {
            $this->append($capabilityName, $businessId, $context->agentId, AuditOutcome::Error, $input, $startedAt);
            throw $e;
        }

        try {
            $result = $handler();
        } catch (Throwable $e) {
            $outcome = $e instanceof PermissionDeniedException ? AuditOutcome::Denied : AuditOutcome::Error;
            $this->append($capabilityName, $businessId, $context->agentId, $outcome, $input, $startedAt);
            throw $e;
        }

        $this->append($capabilityName, $businessId, $context->agentId, AuditOutcome::Success, $input, $startedAt);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function append(string $capabilityName, ?int $businessId, int $coreAgentId, AuditOutcome $status, array $input, float $startedAt): void
    {
        $executionTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->repository->append(
            capabilityName: $capabilityName,
            businessId: $businessId,
            coreAgentId: $coreAgentId,
            status: $status,
            inputSummary: array_values(array_keys($input)),
            executionTimeMs: $executionTimeMs,
        );
    }
}
