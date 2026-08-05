<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * What kind of `AgentMessage` this is — `Delegation` (a task handed to
 * another persona) and `Response` (its outcome) are the two real cases
 * `AgentCommunicationService` writes this stage (Phase 6, Stage 5, §7.30);
 * `Request` is modeled for a future, genuinely bidirectional
 * ask/answer conversation between two personas that isn't itself a
 * delegation (e.g. "what's your current inventory count" without asking
 * the other persona to *do* anything) — the same "modeled but not all
 * reachable yet" shape `StepStatus::Skipped` already carries.
 */
enum MessageType: string
{
    case Request = 'request';
    case Response = 'response';
    case Delegation = 'delegation';
}
