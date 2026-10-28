<?php

namespace IAWP\Overview\Modules;

use IAWP\Chart_Data;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Statistics\Intervals\Intervals;
use IAWP\Statistics\Statistics;
use IAWP\Tables\Table;
/** @internal */
class Line_Chart_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'line-chart';
    }
    public function module_name() : string
    {
        return \__('Line Chart', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $date_range = Relative_Date_Range::range_by_id($this->attributes['date_range'] ?? null);
        $chart_interval = Intervals::default_for($date_range->number_of_days());
        $table_class = Env::get_table($this->report()->type());
        /** @var Table $table */
        $table = new $table_class($this->report()->group_name(), \true);
        $rows_class = $table->group()->rows_class();
        if ($this->report()->has_filters()) {
            $filters = $table->sanitize_filters($this->report()->filters());
        } else {
            $filters = null;
        }
        $rows_query = new $rows_class($date_range, $table->sanitize_sort_parameters(), null, $filters);
        // $rows       = $rows_query->rows();
        $statistics_class = $table->group()->statistics_class();
        /** @var Statistics $statistics */
        $statistics = new $statistics_class($date_range, $this->report()->has_filters() ? $rows_query : null, $chart_interval);
        $chart_data = new Chart_Data($statistics);
        return ['labels' => $chart_data->labels(), 'primary_dataset_id' => $this->attributes['primary_metric'], 'primary_dataset_name' => $this->get_field_option_name('primary_metric'), 'primary_dataset' => $chart_data->metric_dataset($this->attributes['primary_metric']), 'secondary_dataset_id' => $this->attributes['secondary_metric'] ?? null, 'secondary_dataset_name' => $this->get_field_option_name('secondary_metric'), 'secondary_dataset' => $chart_data->metric_dataset($this->attributes['secondary_metric'])];
    }
    protected function module_fields() : array
    {
        return ['report', 'primary_metric', 'secondary_metric', 'date_range'];
    }
}
