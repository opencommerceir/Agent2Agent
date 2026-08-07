<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * PaymentSession::ALLOWED_TRANSITIONS enforces the legal state graph
 * (Pending -> Completed, or Pending -> Failed/Cancelled) — mirrors
 * TransferStatus's own docblock role exactly, one entity over.
 */
enum PaymentSessionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
