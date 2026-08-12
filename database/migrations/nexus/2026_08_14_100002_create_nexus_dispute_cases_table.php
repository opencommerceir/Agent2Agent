<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6/M3 — the real evidence/mediation/arbitration workflow
 * DisputeEscrowAction's own (pre-Phase-6) docblock explicitly earmarked
 * as future territory. One row per disputed Escrow (business_a_id/
 * business_b_id denormalized from it, same self-contained-authorization
 * reasoning Escrow's own docblock already documents for itself).
 * `evidence` is a JSON array of {business_id, note, submitted_at} —
 * plain text notes, not file/attachment storage (no such infra exists
 * anywhere in this codebase), the same "state without a real backing
 * mechanism, honestly documented" pattern Escrow's own docblock
 * establishes for itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_dispute_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_id')->constrained('nexus_escrows')->cascadeOnDelete();
            $table->foreignId('negotiation_id')->constrained('negotiations')->cascadeOnDelete();
            $table->foreignId('business_a_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('business_b_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('opened_by_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->json('evidence');
            $table->string('status')->default('open');
            $table->string('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_dispute_cases');
    }
};
