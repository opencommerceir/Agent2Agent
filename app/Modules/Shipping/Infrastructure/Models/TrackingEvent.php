<?php

namespace App\Modules\Shipping\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent persistence model for the `tracking_events` table.
 * No `updated_at` — an event is immutable (TrackingEvent Entity's own
 * docblock).
 */
class TrackingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tracking_events';

    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
