<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_holding_credit_pool_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->constrained('nexus_holdings')->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('type');
            $table->integer('amount');
            $table->string('reason');
            $table->integer('balance_after');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['holding_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_holding_credit_pool_transactions');
    }
};
