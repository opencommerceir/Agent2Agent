<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_automation_run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained('nexus_automation_rules')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('outcome', 16);
            $table->text('detail');
            // Immutable ledger row — no updated_at, same
            // CreditTransaction/LLMUsageLog/SuspensionRecord audit-trail
            // pattern (Phase 3/M1, Phase 4/M3, Phase 6/M4). Only real
            // outcomes (Triggered/Failed) are recorded, never a "skipped,
            // not due yet" row — an hourly scheduler tick over N rules
            // where nothing happened would otherwise flood this table with
            // zero-information noise.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_automation_run_logs');
    }
};
