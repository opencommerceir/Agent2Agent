<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Carries its own `tenant_id` even though it's always reachable via
     * `report_id` — same reasoning `workflow_logs` gives for doing the
     * same relative to `workflows` (a result is a first-class record
     * queryable directly by tenant, not only through its parent). No
     * `updated_at` — a computed result is immutable; re-running a Report
     * writes a brand new `report_results` row rather than overwriting one
     * (GetReportAction always reads the latest by `generated_at`).
     * `expires_at` is nullable — rule §d.4 ("caching") makes it optional,
     * not every report result needs a TTL.
     */
    public function up(): void
    {
        Schema::create('report_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('result_data');
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();

            $table->index(['tenant_id', 'report_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_results');
    }
};
