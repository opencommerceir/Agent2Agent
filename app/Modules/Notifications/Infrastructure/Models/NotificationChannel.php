<?php

namespace App\Modules\Notifications\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    protected $table = 'notification_channels';

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];
}
