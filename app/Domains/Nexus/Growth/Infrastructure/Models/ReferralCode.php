<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCode extends Model
{
    protected $table = 'nexus_referral_codes';

    protected $fillable = ['business_id', 'code'];
}
