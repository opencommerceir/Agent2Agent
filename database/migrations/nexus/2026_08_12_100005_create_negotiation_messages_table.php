<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negotiation_id')->constrained('negotiations')->cascadeOnDelete();
            $table->foreignId('sender_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type');
            $table->json('terms');
            // Populated by M5's NegotiationReasoningService — nullable now
            // so M3's Actions work standalone before M5 exists.
            $table->json('reasoning')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiation_messages');
    }
};
