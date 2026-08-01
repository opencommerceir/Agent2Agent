<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4, Stage 8 (Performance Optimization, §7.20). The request's own
 * index list was audited against every table's actual migration before
 * writing this one — most of it already existed (added by each table's
 * own original migration), and two entries referenced columns that don't
 * exist at all. Only genuinely missing, schema-correct indexes are added
 * here; see this file's own inline comments for the reasoning per table.
 * Nothing in this migration duplicates an index/unique constraint that
 * already covers the same columns.
 *
 * Skipped entirely (already fully covered by an existing index/unique
 * constraint, confirmed by reading each table's own create-migration):
 * products (tenant_id, sku), customers (tenant_id, email), tax_rates
 * (tenant_id, region), analytics_snapshots (tenant_id, snapshot_date),
 * shipments (tenant_id, status / tenant_id, tracking_number),
 * notification_templates (tenant_id, type, channel_type), and
 * member_roles (member_type, member_id) — the last one also has no
 * tenant_id column at all, unlike what an earlier draft of this
 * migration's own request assumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // orders: existing index(['tenant_id','status']) doesn't cover
        // created_at — this broader composite serves both a
        // tenant+status+recency filter and the narrower tenant+status one.
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'orders_tenant_status_created_at_index');
        });

        // carts: existing index is scoped by owner_type/owner_id (Cart
        // ownership lookups); this is a different access pattern —
        // commerce:check-abandoned-carts / a future Dashboard cart list
        // filtering by tenant+status+recency without going through a
        // specific owner.
        Schema::table('carts', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'carts_tenant_status_created_at_index');
        });

        // tickets: crm.ticket.list accepts both status and customer_id as
        // optional filters together (CRMCapabilities) — a single
        // composite serves that compound filter better than the two
        // existing separate 2-column indexes.
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['tenant_id', 'customer_id', 'status'], 'tickets_tenant_customer_status_index');
        });

        // invoices: existing index(['tenant_id','status']) doesn't cover
        // issued_at — same broadening reasoning as orders above.
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'issued_at'], 'invoices_tenant_status_issued_at_index');
        });

        // loyalty_accounts: unique(['tenant_id','customer_id']) already
        // covers this exactly — nothing to add.

        // point_transactions: neither existing index
        // (['tenant_id','loyalty_account_id'] or
        // ['loyalty_account_id','transaction_type','expires_at']) leads
        // with (tenant_id, expires_at) — ExpirePointsAction/
        // BulkExpirePointsAction's own per-tenant expiry scan benefits
        // from this directly.
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->index(['tenant_id', 'expires_at'], 'point_transactions_tenant_expires_at_index');
        });

        // notifications: notification.message.list accepts both type and
        // status as optional filters together — same compound-filter
        // reasoning as tickets above.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['tenant_id', 'type', 'status'], 'notifications_tenant_type_status_index');
        });

        // kpi_values: the request's own ask — (tenant_id, type,
        // time_period) — references a `type` column that does not exist
        // on kpi_values itself (it lives on the parent kpis table via
        // kpi_id). The real, schema-correct equivalent: existing index
        // (['tenant_id','kpi_id','period_start']) doesn't cover
        // time_period, and CalculateKPIAction's own cache-miss lookup
        // path queries by exactly (tenant_id, kpi_id, time_period).
        Schema::table('kpi_values', function (Blueprint $table) {
            $table->index(['tenant_id', 'kpi_id', 'time_period'], 'kpi_values_tenant_kpi_time_period_index');
        });

        // agents: genuinely had no index at all beyond the unique
        // token_hash constraint — the Dashboard's own Agents page
        // (tenant + status filterable, §7.17) benefits directly.
        Schema::table('agents', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'agents_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_tenant_status_created_at_index'));
        Schema::table('carts', fn (Blueprint $table) => $table->dropIndex('carts_tenant_status_created_at_index'));
        Schema::table('tickets', fn (Blueprint $table) => $table->dropIndex('tickets_tenant_customer_status_index'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex('invoices_tenant_status_issued_at_index'));
        Schema::table('point_transactions', fn (Blueprint $table) => $table->dropIndex('point_transactions_tenant_expires_at_index'));
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('notifications_tenant_type_status_index'));
        Schema::table('kpi_values', fn (Blueprint $table) => $table->dropIndex('kpi_values_tenant_kpi_time_period_index'));
        Schema::table('agents', fn (Blueprint $table) => $table->dropIndex('agents_tenant_status_index'));
    }
};
