<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes a documented gap repeated across Phase 2/M4, Phase 3 §7, and
 * Phase 5 §5 of the handoff log: ApprovePendingNegotiationAction and
 * RejectPendingNegotiationAction could only check `isParty()`, so either
 * side of a Negotiation could resolve a pending-approval pause — not just
 * the Business whose Agent's own authority_limits actually triggered it
 * (AcceptDealAction::execute() knows this at requestApproval() time, it
 * was just never persisted). Nullable because every pre-existing row and
 * every Negotiation that never entered pending_approval has no such
 * Business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            $table->foreignId('pending_approval_business_id')->nullable()->after('rejection_reason')->constrained('businesses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('negotiations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_approval_business_id');
        });
    }
};
