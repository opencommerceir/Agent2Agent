<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `agents.permissions` (Phase 2) is now dead weight: Phase 3's
     * member_roles -> roles -> role_permissions chain
     * (CheckPermissionAction) is the only thing MCP Gateway consults for
     * authorization. Leaving this column in place would be a second,
     * never-read "source of truth" that could silently drift from the
     * real one — dropped rather than left as confusing debt.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('type');
        });
    }
};
