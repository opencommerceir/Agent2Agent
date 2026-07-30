<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->using(RolePermission::class)
            ->withTimestamps();
    }
}
