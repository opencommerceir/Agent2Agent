<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Explicit pivot model (via Role::permissions()->using()) rather than an
 * anonymous pivot, so role_permissions has a first-class place to grow
 * (e.g. granted_by, granted_at) without reshaping the relationship later.
 */
class RolePermission extends Pivot
{
    protected $table = 'role_permissions';

    public $incrementing = true;

    protected $fillable = [
        'role_id',
        'permission_id',
    ];
}
