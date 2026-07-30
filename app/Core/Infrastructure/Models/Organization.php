<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $table = 'organizations';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'owner_user_id',
        'status',
    ];

    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
