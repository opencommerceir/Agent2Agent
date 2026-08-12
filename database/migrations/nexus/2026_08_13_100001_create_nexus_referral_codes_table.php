<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_referral_codes', function (Blueprint $table) {
            $table->id();
            // One code per Business (same 1:1-per-business shape as
            // nexus_credit_balances/nexus_agents).
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_referral_codes');
    }
};
