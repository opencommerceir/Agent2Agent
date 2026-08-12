<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6/M4 — separate from `verification_status` (Phase 1's admin KYC
 * gate): a Business can be verified AND suspended at the same time (fraud
 * doesn't un-verify identity, it revokes standing to transact). Mirrors
 * Core's own Agent::AgentStatus shape (active/suspended), the only
 * existing suspend/activate precedent in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('status')->default('active')->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
