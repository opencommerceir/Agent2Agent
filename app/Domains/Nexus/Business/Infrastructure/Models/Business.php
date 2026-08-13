<?php

namespace App\Domains\Nexus\Business\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `businesses` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on App\Domains\Nexus\Business\Domain\Entities\Business
 * instead.
 */
class Business extends Model
{
    protected $table = 'businesses';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'name_fa',
        'name_en',
        'type',
        'industry',
        'verification_status',
        'status',
        'logo_path',
        'documents',
        'data_residency_region',
    ];

    protected $casts = [
        'documents' => 'array',
    ];
}
