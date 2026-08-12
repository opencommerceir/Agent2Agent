<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Reviews & Ratings. One row per (negotiation, reviewer) pair —
 * a completed deal has exactly two possible reviews (buyer -> seller,
 * seller -> buyer), enforced by a unique index rather than only
 * Application-layer logic, the same defense-in-depth precedent
 * `nexus_credit_purchase_sessions` already applies to its own uniqueness
 * rules. `negotiation_id` (not `contract_id`/`escrow_id`) is the anchor
 * because it's the one id every layer already threads through
 * (NegotiationViewerController, ReleaseEscrowAction) — Contract/Escrow
 * are both reachable from it via existing repositories when needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negotiation_id')->constrained('negotiations')->cascadeOnDelete();
            $table->foreignId('reviewer_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('reviewee_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status')->default('published');
            $table->timestamps();

            $table->unique(['negotiation_id', 'reviewer_business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_reviews');
    }
};
