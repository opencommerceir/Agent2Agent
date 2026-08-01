<?php

namespace App\Modules\Analytics\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsSnapshot extends Model
{
    protected $table = 'analytics_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'snapshot_date', 'total_revenue_amount', 'total_revenue_currency',
        'total_orders', 'total_customers', 'avg_order_value_amount', 'conversion_rate',
        'top_products', 'top_customers', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'conversion_rate' => 'float',
            'top_products' => 'array',
            'top_customers' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
