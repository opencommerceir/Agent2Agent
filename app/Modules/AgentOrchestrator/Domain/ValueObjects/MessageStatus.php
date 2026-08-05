<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * `Pending`/`Received` are modeled for a future genuinely-asynchronous
 * message queue (Phase 6, Stage 5, §7.30's own "Asynchronous Ready"
 * structure — the request-must-first-sit-unread-before-being-picked-up
 * shape those two states describe) but unreached this stage:
 * `AgentCommunicationService` runs every delegation synchronously,
 * in-process, so a message is recorded already `Sent` (the delegation
 * task) or already `Processed` (its response) the instant it's written —
 * there is no real gap in time during which a message sits `Pending` or
 * gets explicitly marked `Received` by a separate reader. The same
 * "modeled but not all reachable yet" shape `TransferStatus::InTransit`
 * (§7.22) already carries.
 */
enum MessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Received = 'received';
    case Processed = 'processed';
}
