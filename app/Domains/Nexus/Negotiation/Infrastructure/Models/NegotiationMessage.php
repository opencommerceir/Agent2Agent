<?php

namespace App\Domains\Nexus\Negotiation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class NegotiationMessage extends Model
{
    protected $table = 'negotiation_messages';

    protected $fillable = [
        'negotiation_id',
        'sender_business_id',
        'type',
        'terms',
        'reasoning',
    ];

    protected $casts = [
        'terms' => 'array',
        'reasoning' => 'array',
    ];

    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }
}
