<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The framework-facing half of User — implements Authenticatable so
 * Laravel's own session guard (`config/auth.php`'s `web` provider) can
 * authenticate against it via `Auth::loginUsingId()`. Password
 * verification itself never happens here or through Laravel's `Hash`
 * facade — AuthenticateUserAction (Application layer) already confirmed
 * the credentials are valid using the Domain's own `HashedPassword` VO
 * before the HTTP layer ever calls `Auth::loginUsingId()`; this model's
 * only job is holding the row Laravel's guard needs to re-resolve the
 * signed-in User on every subsequent request.
 *
 * No 'hashed' cast on `password` — `EloquentUserRepository` always writes
 * an already-bcrypt-hashed string produced by `HashedPassword::fromPlainText()`,
 * so an automatic re-hash-on-set cast would be redundant at best.
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
