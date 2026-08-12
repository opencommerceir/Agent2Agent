<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_escrows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('negotiation_id')->constrained('negotiations')->cascadeOnDelete();
            $table->foreignId('business_a_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('business_b_id')->constrained('businesses')->cascadeOnDelete();
            $table->integer('gross_amount');
            $table->string('currency');
            $table->float('platform_fee_percent');
            $table->integer('platform_fee_amount');
            $table->integer('net_amount');
            $table->string('status')->default('held');
            $table->string('dispute_reason')->nullable();
            $table->timestamp('held_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_escrows');
    }
};
