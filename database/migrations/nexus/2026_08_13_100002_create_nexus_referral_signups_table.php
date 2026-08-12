<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_referral_signups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_business_id')->constrained('businesses')->cascadeOnDelete();
            // A Business can be referred at most once — unique referee.
            $table->foreignId('referee_business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('referral_code', 16);
            $table->string('status', 16)->default('pending');
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_referral_signups');
    }
};
