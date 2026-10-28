<?php

namespace IAWP\AJAX;

use DateTime;
use IAWP\Chart;
use IAWP\Date_Range\Date_Range;
use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Examiner_Config;
use IAWP\Map;
use IAWP\Map_Data;
use IAWP\Quick_Stats;
use IAWP\Rows\Rows;
use IAWP\Statistics\Intervals\Intervals;
use IAWP\Statistics\Statistics;
use IAWP\Tables\Table;
use IAWP\Utils\Timezone;
use Throwable;
/** @internal */
class Filter extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_filter';
    }
    protected function action_required_fields() : array
    {
        return ['table_type', 'columns'];
    }
    protected function action_callback() : void
    {
        $date_range = $this->get_date_range();
        $is_new_date_range = $this->get_field('is_new_date_range') === 'true';
        $filters = $this->get_field('filters') ?? [];
        $sort_column = $this->get_field('sort_column') ?? null;
        $sort_direction = $this->get_field('sort_direction') ?? null;
        $group = $this->get_field('group') ?? null;
        $as_csv = $this->get_field('as_csv') ?? \false;
        $is_new_group = $this->get_field('is_new_group') === 'true';
        $chart_interval = $is_new_date_range ? Intervals::default_for($date_range->number_of_days()) : Intervals::find_by_id($this->get_field('chart_interval'));
        $page = \intval($this->get_field('page') ?? 1);
        $number_of_rows = $page * \IAWPSCOPED\iawp()->pagination_page_size();
        $table_type = $this->get_field('table_type');
        $is_geo_table = $table_type === 'geo';
        $is_journeys_table = $table_type === 'journeys';
        $table_class = Env::get_table();
        $filter_logic = $this->get_filter_logic();
        $examiner_config = Examiner_Config::make(['type' => $this->get_field('examiner_type'), 'group' => $this->get_field('examiner_group'), 'id' => $this->get_int_field('examiner_id')]);
        /** @var Table $table */
        $table = new $table_class($group, $is_new_group);
        $filters = $table->sanitize_filters($filters);
        $filters = $table->resolve_filter_conflicts($filters, $filter_logic);
        $sort_configuration = $table->sanitize_sort_parameters($sort_column, $sort_direction);
        $rows_class = $table->group()->rows_class();
        $statistics_class = $table->group()->statistics_class();
        $get_all_rows = $as_csv || $is_geo_table;
        /** @var Rows $rows_query */
        $rows_query = new $rows_class($date_range, $sort_configuration, $get_all_rows ? null : $number_of_rows, $filters, $filter_logic);
        if ($examiner_config) {
            $rows_query->for_examiner($examiner_config);
        }
        $rows = $rows_query->rows();
        if ($as_csv) {
            $csv = $table->csv($rows, \true);
            echo $csv->to_string();
            return;
        }
        if (empty($filters)) {
            /** @var Statistics $statistics */
            $statistics = new $statistics_class($date_range, null, $chart_interval);
        } else {
            $statistics = new $statistics_class($date_range, $rows_query, $chart_interval);
        }
        $total_number_of_rows = $statistics->total_number_of_rows();
        $table->set_statistics($statistics);
        $quick_stat_statistics = $statistics;
        $hide_unfiltered_statistics = \false;
        // The examiner isn't showing quick stats for the table rows. It's showing quick stats for the
        // examined record.
        if ($examiner_config) {
            $examiner_table_class = Env::get_table($examiner_config->type());
            $examiner_table = new $examiner_table_class($examiner_config->group());
            $examiner_rows_class = $examiner_table->group()->rows_class();
            $examiner_statistics_class = $examiner_table->group()->statistics_class();
            /** @var Rows $rows_query */
            $rows_query = new $examiner_rows_class($date_range, $examiner_table->sanitize_sort_parameters());
            $rows_query->limit_to($examiner_config->id());
            $quick_stat_statistics = new $examiner_statistics_class($date_range, $rows_query, $chart_interval);
            $hide_unfiltered_statistics = \true;
            $table->set_statistics($quick_stat_statistics);
        }
        $chart_markup = null;
        $chart_interval_id = $chart_interval->id();
        if ($is_geo_table) {
            $map_data = new Map_Data($rows);
            $chart = new Map($map_data->get_country_data(), $date_range->label());
            $chart_markup = $chart->get_html();
            $rows = \array_slice($rows, 0, $number_of_rows);
        } elseif ($is_journeys_table) {
            $chart_interval_id = null;
            $chart_markup = null;
        } else {
            $chart = new Chart($quick_stat_statistics);
            $chart_markup = $chart->get_html();
        }
        $quick_stats = new Quick_Stats($quick_stat_statistics, \false, \false, $hide_unfiltered_statistics);
        $raw_filters = \array_map(function (\IAWP\Rows\Filter $filter) {
            return $filter->as_associative_array();
        }, $filters);
        \wp_send_json_success(['stats' => $quick_stats->get_html(), 'chart' => $chart_markup, 'chartInterval' => $chart_interval_id, 'table' => $table->get_rendered_template($rows, \false, $sort_configuration->column(), $sort_configuration->direction()), 'rows' => $table->get_rendered_template($rows, \true, $sort_configuration->column(), $sort_configuration->direction()), 'tableToolbar' => $table->get_table_toolbar_markup(), 'totalNumberOfRows' => $total_number_of_rows, 'label' => $date_range->label(), 'isLastPage' => \count($rows) < \IAWPSCOPED\iawp()->pagination_page_size() * $page, 'columns' => $table->visible_column_ids(), 'columnsHTML' => $table->column_picker_html(), 'groupId' => $table->group()->id(), 'filters' => $raw_filters, 'filtersTemplateHTML' => $table->filters_template_html(), 'filtersButtonsHTML' => $table->filters_condition_buttons_html($filters)]);
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
    private function get_filter_logic() : string
    {
        $value = $this->get_field('filter_logic');
        if (!\in_array($value, ['and', 'or'])) {
            return 'and';
        }
        return $value;
    }
}
