<?php

namespace App\Modules\Analytics\Application\Services;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * A generic "headers + rows -> file bytes" exporter — deliberately knows
 * nothing about KPIs/Analytics semantics, only tabular data, so both
 * `ExportReportAction` (the MCP-facing path, which writes the result to
 * disk and returns a URL) and the Dashboard's own `AnalyticsController`
 * (which streams the same bytes straight back as a download response,
 * no disk round-trip needed for a browser request already holding the
 * connection open) can reuse it identically.
 *
 * PDF rendering uses `barryvdh/laravel-dompdf` (added this stage — no PDF
 * library existed anywhere in this codebase before) against a minimal
 * inline HTML table, not a dedicated Blade view file, since the table
 * shape is entirely generic (headers + rows) and has no other reason to
 * exist as its own view template.
 */
final class ReportExporter
{
    /**
     * @param list<string> $headers
     * @param list<array<int, scalar|null>> $rows
     */
    public function toCsv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    /**
     * @param list<string> $headers
     * @param list<array<int, scalar|null>> $rows
     */
    public function toPdf(string $title, array $headers, array $rows): string
    {
        $html = '<h2>'.e($title).'</h2><table border="1" cellspacing="0" cellpadding="4" width="100%">';
        $html .= '<thead><tr>';

        foreach ($headers as $header) {
            $html .= '<th>'.e($header).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $html .= '<td>'.e((string) $cell).'</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return Pdf::loadHTML($html)->output();
    }
}
