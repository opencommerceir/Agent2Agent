<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A `Report` row is the saved *definition* of a report that was run
     * (type, date range, filters, who ran it) — the computed numbers
     * themselves live in `report_results`, the same "parent
     * definition"/"child result" split `workflows`/`workflow_logs`
     * already establish. No `updated_at` — a Report is never edited
     * after creation, only re-run (which writes a new `report_results`
     * row against the same Report, or a brand new Report — Generate*
     * ReportAction's own docblocks).
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('report_type');
            $table->date('date_range_start');
            $table->date('date_range_end');
            $table->json('filters');
            $table->foreignId('created_by')->constrained('agents')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'report_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
