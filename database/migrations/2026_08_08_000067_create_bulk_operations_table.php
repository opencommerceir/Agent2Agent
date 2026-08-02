<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 3 (Bulk Operations, §7.23). `file_path` is the
 * Agent-supplied source CSV for an import (relative to the `local` disk's
 * `bulk_operations/` directory — no file-upload endpoint exists anywhere
 * in this codebase, an Agent is expected to have placed the file there
 * out of band, the same "file_path is a reference, not an upload" shape
 * every capability that touches storage already has, e.g.
 * `analytics.report.export`'s own `file_url` output); `error_file_path`
 * is this stage's own output, a CSV of just the failed rows + their error
 * messages, written to the `public` disk so `commerce.bulk.get` can hand
 * back a real download URL for it (the same disk `ReportExporter`'s own
 * output already uses, §7.18 — a private/public split for input-vs-output
 * files, not an inconsistency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type');
            $table->string('status');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('file_path')->nullable();
            $table->string('error_file_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('agents');
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_operations');
    }
};
