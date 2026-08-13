<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_holding_subsidiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->constrained('nexus_holdings')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('status', 16)->default('invited');
            $table->timestamp('invited_at');
            $table->timestamp('responded_at')->nullable();

            // A Business can be re-invited to the SAME Holding only if no
            // row for that pair exists yet — one row per (holding,
            // business) pair, not a blanket unique on business_id (that
            // would permanently block a Removed Business from ever
            // rejoining anywhere). "At most one Holding at a time" is
            // enforced at the application layer instead
            // (findActiveOrInvitedByBusinessId), same division of labor
            // CoalitionMember's own unique index already uses.
            $table->unique(['holding_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_holding_subsidiaries');
    }
};
