<?php

namespace App\Domains\Nexus\Negotiation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Negotiation extends Model
{
    protected $table = 'negotiations';

    protected $fillable = [
        'initiator_business_id',
        'initiator_tenant_id',
        'counterparty_business_id',
        'counterparty_tenant_id',
        'catalog_item_type',
        'catalog_item_id',
        'status',
        'current_terms',
        'round_count',
        'max_rounds',
        'rejection_reason',
        'pending_approval_business_id',
    ];

    protected $casts = [
        'current_terms' => 'array',
    ];

    public function messages()
    {
        return $this->hasMany(NegotiationMessage::class);
    }
}
