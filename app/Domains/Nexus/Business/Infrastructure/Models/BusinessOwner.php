<?php

namespace App\Domains\Nexus\Business\Infrastructure\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The `business` auth guard's own Authenticatable — a plain login
 * credential, not a rich Domain entity (it carries no business rules
 * beyond "these credentials belong to this Business," unlike Business
 * itself). Mirrors App\Core\Infrastructure\Models\User's role in the
 * `web` guard exactly, one guard down.
 */
class BusinessOwner extends Authenticatable
{
    use Notifiable;

    protected $table = 'business_owners';

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
