<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `bulk_operation_items` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\BulkOperationItem instead.
 */
class BulkOperationItem extends Model
{
    public $timestamps = false;

    protected $table = 'bulk_operation_items';

    protected $fillable = [
        'bulk_operation_id',
        'row_number',
        'data',
        'status',
        'error_message',
        'entity_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
