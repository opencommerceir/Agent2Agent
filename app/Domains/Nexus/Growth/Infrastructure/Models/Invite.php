<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
    protected $table = 'nexus_invites';

    protected $fillable = [
        'inviter_business_id',
        'invitee_name',
        'invitee_email',
        'referral_code',
        'message_variant',
        'status',
        'converted_business_id',
        'converted_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];
}
