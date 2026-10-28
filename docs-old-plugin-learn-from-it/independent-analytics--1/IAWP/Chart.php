<?php

namespace IAWP;

use IAWP\Statistics\Intervals\Intervals;
use IAWP\Statistics\Statistics;
use IAWP\Utils\Security;
/** @internal */
class Chart
{
    private $statistics;
    private $is_preview;
    private $is_showing_skeleton_ui;
    public function __construct(Statistics $statistics, bool $is_preview = \false, bool $is_showing_skeleton_ui = \false)
    {
        $this->statistics = $statistics;
        $this->is_preview = $is_preview;
        $this->is_showing_skeleton_ui = $is_showing_skeleton_ui;
    }
    public function get_html() : string
    {
        $options = \IAWP\Dashboard_Options::getInstance();
        $primary_statistic = $this->statistics->get_statistic($options->primary_chart_metric_id()) ?? $this->statistics->get_statistic('visitors');
        $secondary_statistic = \is_string($options->secondary_chart_metric_id()) ? $this->statistics->get_statistic($options->secondary_chart_metric_id()) : null;
        // Exception for clicks report
        if ($primary_statistic && $primary_statistic->is_invisible()) {
            $primary_statistic = $this->statistics->get_statistic('clicks');
        }
        // Exception for clicks report
        if ($secondary_statistic && $secondary_statistic->is_invisible()) {
            $secondary_statistic = null;
        }
        $labels = \array_map(function ($data_point) {
            return Security::json_encode($this->statistics->chart_interval()->get_label_for($data_point[0]));
        }, $primary_statistic->statistic_over_time());
        $data = [];
        foreach ($this->statistics->get_statistics() as $statistic) {
            if ($this->is_showing_skeleton_ui) {
                $data[$statistic->id()] = [];
                continue;
            }
            $data[$statistic->id()] = \array_map(function ($data_point) {
                return $data_point[1];
            }, $statistic->statistic_over_time());
        }
        $total_chart_statistics = 0;
        foreach ($this->statistics->get_grouped_statistics() as $group) {
            $total_chart_statistics += \count($group['items']);
        }
        return \IAWPSCOPED\iawp_blade()->run('chart', ['chart' => $this, 'intervals' => Intervals::all(), 'current_interval' => $this->statistics->chart_interval(), 'available_datasets' => $this->statistics->get_grouped_statistics(), 'primary_chart_metric_id' => $primary_statistic->id(), 'secondary_chart_metric_id' => \is_null($secondary_statistic) ? null : $secondary_statistic->id(), 'stimulus_values' => ['locale' => \get_bloginfo('language'), 'currency' => \IAWPSCOPED\iawp()->get_currency_code(), 'is-preview' => $this->is_preview() ? '1' : '0', 'disable-dark-mode' => $this->is_preview() ? '1' : '0', 'primary-chart-metric-id' => $primary_statistic->id(), 'primary-chart-metric-name' => $primary_statistic->name(), 'secondary-chart-metric-id' => \is_null($secondary_statistic) ? null : $secondary_statistic->id(), 'secondary-chart-metric-name' => \is_null($secondary_statistic) ? null : $secondary_statistic->name(), 'labels' => $labels, 'data' => $data, 'has-multiple-datasets' => $total_chart_statistics > 1 ? 1 : 0]]);
    }
    public function is_preview() : bool
    {
        return $this->is_preview;
    }
    public function encode_json(array $array) : string
    {
        return Security::json_encode($array);
    }
}
