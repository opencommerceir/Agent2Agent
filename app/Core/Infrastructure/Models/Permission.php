<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'key',
        'description',
    ];
}
