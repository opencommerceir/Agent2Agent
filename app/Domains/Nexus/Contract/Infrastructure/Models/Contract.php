<?php

namespace App\Domains\Nexus\Contract\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $table = 'contracts';

    protected $fillable = [
        'negotiation_id',
        'business_a_id',
        'business_b_id',
        'terms',
        'content_hash',
        'pdf_path',
        'signed_at',
    ];

    protected $casts = [
        'terms' => 'array',
        'signed_at' => 'datetime',
    ];
}
