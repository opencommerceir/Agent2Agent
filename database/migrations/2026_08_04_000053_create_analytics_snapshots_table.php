<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedBigInteger('total_revenue_amount')->default(0);
            $table->string('total_revenue_currency', 3)->default('USD');
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('total_customers')->default(0);
            $table->unsignedBigInteger('avg_order_value_amount')->default(0);
            $table->decimal('conversion_rate', 5, 2)->nullable();
            $table->json('top_products')->nullable();
            $table->json('top_customers')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
