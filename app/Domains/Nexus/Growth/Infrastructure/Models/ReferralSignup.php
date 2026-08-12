<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSignup extends Model
{
    protected $table = 'nexus_referral_signups';

    protected $fillable = [
        'referrer_business_id',
        'referee_business_id',
        'referral_code',
        'status',
        'rewarded_at',
    ];

    protected $casts = [
        'rewarded_at' => 'datetime',
    ];
}
