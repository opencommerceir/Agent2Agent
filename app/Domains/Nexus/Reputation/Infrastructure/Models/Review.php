<?php

namespace App\Domains\Nexus\Reputation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'nexus_reviews';

    protected $fillable = [
        'negotiation_id',
        'reviewer_business_id',
        'reviewee_business_id',
        'rating',
        'comment',
        'status',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];
}
