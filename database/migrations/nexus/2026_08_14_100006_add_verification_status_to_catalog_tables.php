<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6/M5 — Verification System's "تأیید محصولات/خدمات" line. Own
 * column per table (not a shared Catalog-wide table) since Product/Service
 * already don't share a table — same reasoning every other Catalog field
 * duplicates across the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_products', function (Blueprint $table) {
            $table->string('verification_status')->default('pending')->after('attributes');
        });

        Schema::table('nexus_services', function (Blueprint $table) {
            $table->string('verification_status')->default('pending')->after('attributes');
        });
    }

    public function down(): void
    {
        Schema::table('nexus_products', function (Blueprint $table) {
            $table->dropColumn('verification_status');
        });

        Schema::table('nexus_services', function (Blueprint $table) {
            $table->dropColumn('verification_status');
        });
    }
};
