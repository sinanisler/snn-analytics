<?php

namespace IAWP;

use IAWP\Statistics\Statistics;
use IAWP\Utils\Security;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Chart_Data
{
    private $statistics;
    private $labels;
    private $metric_datasets;
    public function __construct(Statistics $statistics)
    {
        $this->statistics = $statistics;
        $this->labels = $this->generate_labels();
        $this->metric_datasets = $this->generate_metric_datasets();
    }
    /**
     * @return string[]
     */
    public function labels() : array
    {
        return $this->labels;
    }
    public function metric_datasets() : array
    {
        return $this->metric_datasets;
    }
    public function metric_dataset(string $metric_id) : ?array
    {
        return $this->metric_datasets[$metric_id] ?? null;
    }
    /**
     * Generate an array of chart labels.
     *
     * @return array
     */
    private function generate_labels() : array
    {
        $some_statistic = $this->statistics->get_statistic('visitors', 'clicks');
        return Collection::make($some_statistic->statistic_over_time())->map(function ($data_point) {
            $label = $this->statistics->chart_interval()->get_label_for($data_point[0]);
            return Security::json_encode($label);
        })->all();
    }
    /**
     * Generate a dataset for each metric.
     *
     * @return array
     */
    private function generate_metric_datasets() : array
    {
        $metric_datasets = [];
        foreach ($this->statistics->get_statistics() as $statistic) {
            $metric_datasets[$statistic->id()] = Collection::make($statistic->statistic_over_time())->map(function ($data_point) {
                return $data_point[1];
            })->all();
        }
        return $metric_datasets;
    }
}
