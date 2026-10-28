<?php

namespace IAWP\Overview\Modules;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Tables\Table;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Pie_Chart_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'pie-chart';
    }
    public function module_name() : string
    {
        return \__('Pie Chart', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $sort_by = $this->attributes['aggregatable_sort_by'] ?? null;
        $sort_direction = $this->attributes['sort_direction'] ?? null;
        $date_range = Relative_Date_Range::range_by_id($this->attributes['date_range'] ?? null);
        $table_class = Env::get_table($this->report()->type());
        /** @var Table $table */
        $table = new $table_class($this->report()->group_name(), \true);
        $sort_configuration = $table->sanitize_sort_parameters($sort_by, $sort_direction);
        $metric_column = $sort_configuration->column();
        if ($this->report()->has_filters()) {
            $filters = $table->sanitize_filters($this->report()->filters());
        } else {
            $filters = null;
        }
        $rows_class = $table->group()->rows_class();
        $rows_query = new $rows_class($date_range, $sort_configuration, null, $filters);
        $rows = Collection::make($rows_query->rows());
        $dataset = $rows->take(4)->map(function ($model) use($table, $metric_column) {
            $title_column = $table->group()->title_column();
            return ['label' => $model->{$title_column}(), 'unit' => $this->get_field_option_name('aggregatable_sort_by'), 'value' => $model->{$metric_column}(), 'formatted_value' => $table->formatted_csv_cell_content($table->get_column($metric_column), $model->{$metric_column}())];
        })->all();
        // Add up the rest of the rows to get value for "Other"
        $other = $rows->slice(4)->reduce(function ($sum, $model) use($metric_column) {
            return $sum + $model->{$metric_column}();
        }, 0);
        // Include an "Other" wedge if a value was found
        if ($other > 0) {
            $dataset[] = ['label' => \__('Other', 'independent-analytics'), 'unit' => $this->get_field_option_name('aggregatable_sort_by'), 'value' => $other, 'formatted_value' => $table->formatted_csv_cell_content($table->get_column($metric_column), $other)];
        }
        return $dataset;
    }
    protected function module_fields() : array
    {
        return ['report', 'aggregatable_sort_by', 'date_range'];
    }
}
