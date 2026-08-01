<?php

namespace App\Modules\Analytics\Application\DTOs;

/**
 * The exact `{labels: [...], data: [...]}` shape Chart.js's own
 * `data.labels`/`data.datasets[0].data` expect — built by
 * `ChartDataProvider`, consumed directly by a Blade view's inline
 * `json_encode($chartData['labels'])` call (this stage's own request
 * shows that exact pattern).
 */
final class ChartData
{
    /**
     * @param list<string> $labels
     * @param list<int|float> $data
     */
    public function __construct(
        public readonly array $labels,
        public readonly array $data,
    ) {
    }

    /**
     * @return array{labels: list<string>, data: list<int|float>}
     */
    public function toArray(): array
    {
        return [
            'labels' => $this->labels,
            'data' => $this->data,
        ];
    }
}
