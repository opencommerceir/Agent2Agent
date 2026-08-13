<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

/**
 * "خروجی گزارش" (report export, roadmap Phase 8) — a flat CSV of
 * GetBusinessAnalyticsAction's own numbers, nothing recomputed here. CSV,
 * not a second PDF pipeline: Contract's own PDF (Phase 2/M6) exists to
 * produce a signed legal artifact from a frozen snapshot, a fundamentally
 * different job from a self-service data export a Business owner re-runs
 * whenever they like — a plain, honest, spreadsheet-openable table is the
 * right tool here, not a document engine.
 */
final class ExportBusinessAnalyticsReportAction
{
    public function __construct(
        private readonly GetBusinessAnalyticsAction $getBusinessAnalytics,
    ) {
    }

    public function execute(int $businessId): string
    {
        $data = $this->getBusinessAnalytics->execute($businessId);

        $rows = [
            ['metric', 'value'],
            ['success_rate_percent', round($data['successRate'] * 100, 1)],
            ['completed_deals', $data['completedDeals']],
            ['deals_accepted', $data['dealCounts']['accepted']],
            ['deals_rejected', $data['dealCounts']['rejected']],
            ['deals_expired', $data['dealCounts']['expired']],
            ['deals_open', $data['dealCounts']['open']],
            ['negotiated_deals_with_savings', $data['savings']['dealCount']],
        ];

        foreach ($data['savings']['totalsByCurrency'] as $currency => $amount) {
            $rows[] = ["savings_total_{$currency}", $amount];
        }

        foreach (['product', 'service'] as $type) {
            $benchmark = $data['priceBenchmark'][$type];
            $rows[] = ["{$type}_own_average_price_{$benchmark['currency']}", $benchmark['ownAverageAmount'] ?? 'n/a'];
            $rows[] = ["{$type}_industry_average_price_{$benchmark['currency']}", $benchmark['industryAverageAmount'] ?? 'n/a'];
        }

        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
