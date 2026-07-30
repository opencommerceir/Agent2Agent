<?php

namespace App\Core\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationMember extends Model
{
    protected $table = 'organization_members';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'member_id',
        'member_type',
        'role_in_org',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
