<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6/M4 — the human recourse the roadmap's "Auto-suspension همراه با
 * appeal process" line requires. One row per appeal attempt (a Business
 * can appeal again after a Denied outcome — no uniqueness constraint,
 * unlike Reviews' one-per-negotiation rule, since a suspension can
 * recur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_suspension_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->text('message');
            $table->string('status')->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_suspension_appeals');
    }
};
