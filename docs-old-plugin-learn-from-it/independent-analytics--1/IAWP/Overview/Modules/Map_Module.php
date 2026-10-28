<?php

namespace IAWP\Overview\Modules;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Map_Data;
use IAWP\Tables\Table;
/** @internal */
class Map_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'map';
    }
    public function module_name() : string
    {
        return \__('World Map', 'independent-analytics');
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
        $rows_query = new $rows_class($date_range, $sort_configuration, null, $filters);
        $rows = $rows_query->rows();
        $map_data = new Map_Data($rows);
        return $map_data->get_country_data();
    }
    protected function module_fields() : array
    {
        return ['geo_report', 'date_range'];
    }
}
