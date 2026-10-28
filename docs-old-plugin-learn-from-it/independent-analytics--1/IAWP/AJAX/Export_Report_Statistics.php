<?php

namespace IAWP\AJAX;

use DateTime;
use IAWP\Date_Range\Date_Range;
use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Examiner_Config;
use IAWP\Statistics\Intervals\Intervals;
use IAWP\Statistics\Statistics;
use IAWP\Tables\Table;
use IAWP\Utils\Timezone;
use Throwable;
/** @internal */
class Export_Report_Statistics extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_export_report_statistics';
    }
    protected function action_callback() : void
    {
        $date_range = $this->get_date_range();
        $is_new_date_range = $this->get_field('is_new_date_range') === 'true';
        $filters = $this->get_field('filters') ?? [];
        $sort_column = $this->get_field('sort_column') ?? null;
        $sort_direction = $this->get_field('sort_direction') ?? null;
        $group = $this->get_field('group') ?? null;
        $chart_interval = $is_new_date_range ? Intervals::default_for($date_range->number_of_days()) : Intervals::find_by_id($this->get_field('chart_interval'));
        $page = \intval($this->get_field('page') ?? 1);
        $number_of_rows = $page * \IAWPSCOPED\iawp()->pagination_page_size();
        $table_type = $this->get_field('table_type');
        $is_geo_table = $table_type === 'geo';
        $examiner_config = Examiner_Config::make(['type' => $this->get_field('examiner_type'), 'group' => $this->get_field('examiner_group'), 'id' => $this->get_int_field('examiner_id')]);
        if ($examiner_config) {
            $table_class = Env::get_table($examiner_config->type());
            /** @var Table $table */
            $table = new $table_class($examiner_config->group());
        } else {
            $table_class = Env::get_table();
            /** @var Table $table */
            $table = new $table_class($group);
        }
        $filters = $table->sanitize_filters($filters);
        $sort_configuration = $table->sanitize_sort_parameters($sort_column, $sort_direction);
        $rows_class = $table->group()->rows_class();
        $statistics_class = $table->group()->statistics_class();
        if ($is_geo_table) {
            $rows_query = new $rows_class($date_range, $sort_configuration, null, $filters);
        } else {
            $rows_query = new $rows_class($date_range, $sort_configuration, $number_of_rows, $filters);
        }
        if ($examiner_config) {
            $rows_query->limit_to($examiner_config->id());
        }
        if (empty($filters) && !$examiner_config) {
            /** @var Statistics $statistics */
            $statistics = new $statistics_class($date_range, null, $chart_interval);
        } else {
            $statistics = new $statistics_class($date_range, $rows_query, $chart_interval);
        }
        \wp_send_json_success(['csv' => $statistics->get_statistics_as_csv()->to_string()]);
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
