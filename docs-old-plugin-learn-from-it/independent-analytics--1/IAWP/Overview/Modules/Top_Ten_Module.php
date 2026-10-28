<?php

namespace IAWP\Overview\Modules;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Tables\Table;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Top_Ten_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'top-ten';
    }
    public function module_name() : string
    {
        return \__('Top 10 List', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $sort_by = $this->attributes['sort_by'] ?? null;
        $sort_direction = $this->attributes['sort_direction'] ?? null;
        $date_range = Relative_Date_Range::range_by_id($this->attributes['date_range'] ?? null);
        $table_class = Env::get_table($this->report()->type());
        /** @var Table $table */
        $table = new $table_class($this->report()->group_name(), \true);
        $sort_configuration = $table->sanitize_sort_parameters($sort_by, $sort_direction);
        if ($this->report()->has_filters()) {
            $filters = $table->sanitize_filters($this->report()->filters());
        } else {
            $filters = null;
        }
        $rows_class = $table->group()->rows_class();
        $rows_query = new $rows_class($date_range, $sort_configuration, 10, $filters);
        $rows = $rows_query->rows();
        return Collection::make($rows)->map(function ($model) use($table, $sort_configuration) {
            $title_column = $table->group()->title_column();
            $metric_column = $sort_configuration->column();
            return [$model->{$title_column}(), $table->formatted_csv_cell_content($table->get_column($metric_column), $model->{$metric_column}())];
        })->all();
    }
    public function primary_column_name() : string
    {
        $table_class = Env::get_table($this->report()->type());
        /** @var Table $table */
        $table = new $table_class($this->report()->group_name(), \true);
        $title_column = $table->group()->title_column();
        $column = $table->get_column($title_column);
        return $column->name();
    }
    public function metric_column_name() : string
    {
        $table_class = Env::get_table($this->report()->type());
        /** @var Table $table */
        $table = new $table_class($this->report()->group_name(), \true);
        $column = $table->get_column($this->attributes['sort_by']);
        return $column->name();
    }
    protected function module_fields() : array
    {
        return ['report', 'sort_by', 'sort_direction', 'date_range'];
    }
}
