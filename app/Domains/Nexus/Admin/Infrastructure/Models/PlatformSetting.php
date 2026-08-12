<?php

namespace App\Domains\Nexus\Admin\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the `nexus_platform_settings` table.
 * Never used directly outside the Infrastructure layer — the rest of the
 * application depends on
 * App\Domains\Nexus\Admin\Domain\Entities\PlatformSetting instead.
 */
class PlatformSetting extends Model
{
    protected $table = 'nexus_platform_settings';

    protected $fillable = [
        'key',
        'value',
    ];
}
