<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_credit_balances', function (Blueprint $table) {
            $table->id();
            // One balance per Business (same 1:1-per-business shape as
            // nexus_agents) — no tenant_id column, business_id already
            // implies a single tenant.
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_credit_balances');
    }
};
