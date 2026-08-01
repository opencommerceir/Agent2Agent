<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('kpi_id')->constrained('kpis')->cascadeOnDelete();
            $table->integer('value_amount');
            $table->string('value_currency', 3);
            $table->string('time_period');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('calculated_at');
            $table->json('metadata')->nullable();

            $table->index(['tenant_id', 'kpi_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_values');
    }
};
