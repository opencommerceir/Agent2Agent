<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class CoalitionMember extends Model
{
    protected $table = 'nexus_coalition_members';

    public $timestamps = false;

    protected $fillable = ['coalition_id', 'business_id', 'quantity', 'joined_at'];

    protected $casts = [
        'joined_at' => 'datetime',
    ];
}
