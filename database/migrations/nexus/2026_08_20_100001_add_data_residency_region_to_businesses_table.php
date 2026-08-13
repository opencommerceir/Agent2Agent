<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7/M10 — nullable: most existing Businesses have never declared a
 * region (null means "not declared yet", never a default region — a
 * defaulted value here would be a fabricated compliance claim, the same
 * "don't build a signal that isn't real" restraint Phase 5/M5 applied to
 * Cohort's `created_at` fallback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('data_residency_region')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('data_residency_region');
        });
    }
};
