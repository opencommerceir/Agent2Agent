<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Capability extends Model
{
    protected $table = 'capabilities';

    protected $fillable = [
        'name',
        'description',
        'input_schema',
        'output_schema',
        'required_permissions',
    ];

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'output_schema' => 'array',
            'required_permissions' => 'array',
        ];
    }
}
