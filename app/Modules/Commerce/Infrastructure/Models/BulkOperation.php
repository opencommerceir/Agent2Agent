<?php

namespace App\Modules\Commerce\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent persistence model for the `bulk_operations` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Modules\Commerce\Domain\Entities\BulkOperation instead.
 */
class BulkOperation extends Model
{
    protected $table = 'bulk_operations';

    protected $fillable = [
        'tenant_id',
        'type',
        'status',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'file_path',
        'error_file_path',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkOperationItem::class);
    }
}
