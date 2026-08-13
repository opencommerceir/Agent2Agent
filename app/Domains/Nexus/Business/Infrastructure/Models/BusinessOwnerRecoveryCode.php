<?php

namespace App\Domains\Nexus\Business\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per unused recovery code — VerifyMfaChallengeAction deletes the
 * row on successful use (not a soft "used_at" flag left forever), so a
 * `remaining count` is just a row count. `used_at` still exists for the
 * rare TOCTOU-safe update-then-check path (see VerifyMfaChallengeAction).
 */
class BusinessOwnerRecoveryCode extends Model
{
    protected $table = 'business_owner_recovery_codes';

    public $timestamps = false;

    protected $fillable = [
        'business_owner_id',
        'code_hash',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
