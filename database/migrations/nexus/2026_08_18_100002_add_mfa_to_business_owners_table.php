<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_owners', function (Blueprint $table) {
            // Laravel's built-in `encrypted` cast (first use in this
            // codebase) — the secret is only ever read back by TotpService
            // to compute a code, never displayed again after setup.
            $table->text('mfa_secret')->nullable()->after('must_change_password');
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_secret');
        });
    }

    public function down(): void
    {
        Schema::table('business_owners', function (Blueprint $table) {
            $table->dropColumn(['mfa_secret', 'mfa_enabled_at']);
        });
    }
};
