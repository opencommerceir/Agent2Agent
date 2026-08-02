<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * WarehouseTransfer::ALLOWED_TRANSITIONS enforces the legal state graph
 * (Pending -> Approved -> Completed, or Cancelled from any non-terminal
 * state). InTransit is modeled (rule §e.3's own "Request -> Approve ->
 * Reserve -> In Transit -> Complete" narrative) but unreached by any
 * Action this stage — only Request/Approve/Complete were requested, the
 * same "modeled but not all reachable" gap Loyalty's
 * RewardType::FreeProduct/FreeShipping and Redemption's pending/cancelled
 * states already carry (HANDOFF §7.10 pattern #29). A future
 * MarkTransferInTransitAction would insert between Approved and Completed
 * without needing any change here.
 */
enum TransferStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
