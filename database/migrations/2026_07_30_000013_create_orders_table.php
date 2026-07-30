<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * agent_id has no cascade/nullOnDelete: Orders are permanent business
     * records, not something that should disappear (cascade) or lose
     * their audit trail (nullOnDelete) if the placing Agent is ever
     * removed — the default RESTRICT behavior blocks that deletion
     * instead, same reasoning products/categories give tenant_id but
     * deliberately not extended to agent_id here.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents');
            $table->string('order_number');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('subtotal_amount');
            $table->string('subtotal_currency', 3);
            $table->unsignedBigInteger('total_amount');
            $table->string('total_currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
