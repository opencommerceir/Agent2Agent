<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 Stage 4 (i18n) — additive, backward-compatible (HANDOFF §3
 * pattern #6): every existing Tenant row gets 'en' via the column default,
 * so nothing about Tenant registration/lookup changes for a caller that
 * never touches this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('default_language', 2)->default('en')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('default_language');
        });
    }
};
