<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('invitee_name');
            $table->string('invitee_email');
            $table->string('referral_code', 16);
            $table->string('message_variant', 8)->default('a');
            $table->string('status', 16)->default('sent');
            $table->foreignId('converted_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['referral_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_invites');
    }
};
