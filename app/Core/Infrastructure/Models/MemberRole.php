<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class MemberRole extends Model
{
    protected $table = 'member_roles';

    protected $fillable = [
        'member_type',
        'member_id',
        'role_id',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
