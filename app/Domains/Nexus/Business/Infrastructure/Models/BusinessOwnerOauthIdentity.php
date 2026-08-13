<?php

namespace App\Domains\Nexus\Business\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Same "plain Eloquent, no Domain entity" shape BusinessOwner itself uses —
 * this table is a login-linkage record, not a business rule.
 */
class BusinessOwnerOauthIdentity extends Model
{
    protected $table = 'business_owner_oauth_identities';

    public $timestamps = false;

    protected $fillable = [
        'business_owner_id',
        'provider',
        'provider_user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
