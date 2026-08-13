<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTemplateInstall extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'nexus_agent_template_installs';

    protected $fillable = [
        'template_id',
        'installing_business_id',
        'publisher_business_id',
        'price_credits',
        'platform_fee_credits',
        'publisher_earnings_credits',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
