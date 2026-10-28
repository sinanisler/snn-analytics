<?php

namespace IAWP\AJAX;

use DateTime;
use IAWP\Date_Range\Date_Range;
use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Examiner_Config;
use IAWP\Tables\Table;
use IAWP\Utils\Timezone;
use Throwable;
/** @internal */
class Export_Report_Table extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_export_report_table';
    }
    protected function action_callback() : void
    {
        $date_range = $this->get_date_range();
        $filters = $this->get_field('filters') ?? [];
        $sort_column = $this->get_field('sort_column') ?? null;
        $sort_direction = $this->get_field('sort_direction') ?? null;
        $group = $this->get_field('group') ?? null;
        $table_class = Env::get_table();
        $examiner_config = Examiner_Config::make(['type' => $this->get_field('examiner_type'), 'group' => $this->get_field('examiner_group'), 'id' => $this->get_int_field('examiner_id')]);
        /** @var Table $table */
        $table = new $table_class($group);
        $filters = $table->sanitize_filters($filters);
        $sort_configuration = $table->sanitize_sort_parameters($sort_column, $sort_direction);
        $rows_class = $table->group()->rows_class();
        $rows_query = new $rows_class($date_range, $sort_configuration, null, $filters);
        if ($examiner_config) {
            $rows_query->for_examiner($examiner_config);
        }
        $rows = $rows_query->rows();
        $csv = $table->csv($rows, \true)->to_string();
        $csv = \wp_kses($csv, 'strip');
        $csv = \str_replace('&amp;', '&', $csv);
        \wp_send_json_success(['csv' => $csv]);
    }
    /**
     * Get the date range for the filter request
     *
     * The date info can be supplied in one of two ways.
     *
     * The first is to provide a relative_range_id which is converted into start, end, and label.
     *
     * The second is to provide explicit start and end fields which will be used as is.
     *
     * @return Date_Range
     */
    private function get_date_range() : Date_Range
    {
        $relative_range_id = $this->get_field('relative_range_id');
        $exact_start = $this->get_field('exact_start');
        $exact_end = $this->get_field('exact_end');
        if (!\is_null($exact_start) && !\is_null($exact_end)) {
            try {
                $start = new DateTime($exact_start, Timezone::site_timezone());
                $end = new DateTime($exact_end, Timezone::site_timezone());
                return new Exact_Date_Range($start, $end);
            } catch (Throwable $e) {
                // Do nothing and fall back to default relative date range
            }
        }
        return new Relative_Date_Range($relative_range_id);
    }
}
