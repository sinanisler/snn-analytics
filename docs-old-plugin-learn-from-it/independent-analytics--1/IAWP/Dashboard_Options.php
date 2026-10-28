<?php

namespace IAWP;

use DateTime;
use IAWP\Date_Range\Date_Range;
use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Rows\Filter;
use IAWP\Statistics\Intervals\Interval;
use IAWP\Statistics\Intervals\Intervals;
use IAWP\Utils\Request;
use IAWP\Utils\Singleton;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Support\Collection;
use Throwable;
/**
 * Dashboards support various options via the search query string portion of the URL.
 *
 * The Dashboard_Options class give you an interface for fetching any set values or falling back
 * to a default value as needed.
 * @internal
 */
class Dashboard_Options
{
    use Singleton;
    private $report;
    private static $default_visible_quick_stats = ['visitors', 'views', 'sessions', 'average_session_duration', 'bounce_rate', 'views_per_session', 'wc_orders', 'wc_net_sales'];
    private function __construct()
    {
        $this->report = $this->get_report();
    }
    public function report_name() : ?string
    {
        if (\is_null($this->report->name ?? null)) {
            return 'Report';
        }
        return $this->report->name;
    }
    public function visible_columns() : ?array
    {
        if (Request::get_post_array('columns')) {
            return Request::get_post_array('columns');
        }
        if (\is_null($this->report) || \is_null($this->report->columns)) {
            return null;
        }
        return \json_decode($this->report->columns, \true);
    }
    public function visible_quick_stats() : array
    {
        if (Request::get_post_array('quick_stats')) {
            return Request::get_post_array('quick_stats');
        }
        $decoded_value = \json_decode($this->report->quick_stats ?? 'null', \true);
        if (\is_array($decoded_value)) {
            return $decoded_value;
        }
        if (\IAWP\Env::get_tab() === 'clicks') {
            return ['clicks'];
        }
        return self::$default_visible_quick_stats;
    }
    public function primary_chart_metric_id() : string
    {
        if (Request::get_post_string('primary_chart_metric_id')) {
            return Request::get_post_string('primary_chart_metric_id');
        }
        if (\is_null($this->report->primary_chart_metric_id ?? null)) {
            return 'visitors';
        }
        return $this->report->primary_chart_metric_id;
    }
    public function secondary_chart_metric_id() : ?string
    {
        if (Request::get_post_string('secondary_chart_metric_id')) {
            return Request::get_post_string('secondary_chart_metric_id');
        }
        if (\is_null($this->report->secondary_chart_metric_id ?? null)) {
            return 'views';
        }
        return $this->report->secondary_chart_metric_id;
    }
    public function filters() : array
    {
        if (\is_null($this->report) || \is_null($this->report->filters)) {
            return [];
        }
        $table_class = \IAWP\Env::get_table($this->report->type);
        $table = new $table_class($this->report->group_name ?? null);
        $filters = \json_decode($this->report->filters, \true);
        if ($filters === null) {
            return [];
        }
        return $table->sanitize_filters($filters);
    }
    public function raw_filters() : array
    {
        return Collection::make($this->filters())->map(function (Filter $filter) {
            return $filter->as_associative_array();
        })->all();
    }
    public function filter_logic() : string
    {
        return $this->report->filter_logic ?? 'and';
    }
    public function sort_column() : ?string
    {
        return $this->report->sort_column ?? null;
    }
    public function sort_direction() : ?string
    {
        return $this->report->sort_direction ?? null;
    }
    public function group() : ?string
    {
        return $this->report->group_name ?? null;
    }
    public function chart_interval() : ?Interval
    {
        if (\is_null($this->report->chart_interval ?? null)) {
            return Intervals::default_for($this->get_date_range()->number_of_days());
        }
        return Intervals::find_by_id($this->report->chart_interval);
    }
    /**
     * @return Date_Range
     */
    public function get_date_range() : Date_Range
    {
        if ($this->has_exact_range()) {
            try {
                $start = new DateTime($this->start(), Timezone::site_timezone());
                $end = new DateTime($this->end(), Timezone::site_timezone());
                return new Exact_Date_Range($start, $end);
            } catch (Throwable $e) {
                // Do nothing and fall back to default relative date range
            }
        }
        return new Relative_Date_Range($this->relative_range_id());
    }
    public function start() : ?string
    {
        if (!$this->has_exact_range()) {
            return null;
        }
        return $this->report->exact_start;
    }
    public function end() : ?string
    {
        if (!$this->has_exact_range()) {
            return null;
        }
        return $this->report->exact_end;
    }
    /**
     * Prefer exact range to relative range if both are provided
     */
    public function relative_range_id() : ?string
    {
        if ($this->has_exact_range()) {
            return null;
        }
        $relative_range_id = $this->report->relative_range_id ?? null;
        $default = 'LAST_THIRTY';
        if (\IAWP\Env::get_tab() === 'journeys') {
            $default = 'TODAY';
        }
        if ($relative_range_id === null) {
            return $default;
        }
        if (Relative_Date_Range::is_valid_range($relative_range_id) === \false) {
            return $default;
        }
        return $relative_range_id;
    }
    public function maybe_redirect() : void
    {
        if (\IAWP\Env::get_page() !== 'independent-analytics') {
            return;
        }
        if (isset($_GET['examiner'])) {
            return;
        }
        if (empty($_GET['report']) && empty($_GET['tab'])) {
            $favorite_report = \IAWP\Report_Finder::new()->get_favorited_report();
            if (\is_null($favorite_report)) {
                return;
            }
            \wp_safe_redirect($favorite_report->url());
            exit;
        }
        if (!empty($_GET['report']) && \is_null($this->report)) {
            \wp_safe_redirect(\IAWPSCOPED\iawp_dashboard_url(['tab' => \IAWP\Env::get_tab()]));
            exit;
        }
        if (!\is_null($this->report) && \IAWP\Env::get_tab() !== $this->report->type) {
            \wp_safe_redirect(\IAWPSCOPED\iawp_dashboard_url(['tab' => $this->report->type, 'report' => $this->report->report_id]));
            exit;
        }
    }
    public function is_sidebar_collapsed() : bool
    {
        $is_sidebar_collapsed = \get_user_meta(\get_current_user_id(), 'iawp_is_sidebar_collapsed', \true) === '1';
        return $is_sidebar_collapsed;
    }
    public function is_examiner() : bool
    {
        if (!\IAWPSCOPED\iawp_is_pro()) {
            return \false;
        }
        return \array_key_exists('examiner', $_GET);
    }
    private function has_exact_range() : bool
    {
        return !\is_null($this->report->exact_start ?? null) && !\is_null($this->report->exact_end ?? null);
    }
    private function get_report() : ?object
    {
        if ($this->is_examiner()) {
            return $this->build_examiner_report();
        }
        $id = \filter_input(\INPUT_GET, 'report', \FILTER_VALIDATE_INT);
        if (\is_int($id)) {
            return $this->fetch_saved_report($id);
        }
        return null;
    }
    private function build_examiner_report() : object
    {
        return (object) ['exact_start' => Request::query('exact_start'), 'exact_end' => Request::query('exact_end'), 'relative_range_id' => Request::query('relative_range_id'), 'quick_stats' => \json_encode(Request::query_array('quick_stats')), 'primary_chart_metric_id' => Request::query('primary_chart_metric_id'), 'secondary_chart_metric_id' => Request::query('secondary_chart_metric_id'), 'chart_interval' => Request::query('chart_interval'), 'group_name' => Request::query('group'), 'filters' => null, 'columns' => null, 'type' => Request::query('tab')];
    }
    private function fetch_saved_report(int $id) : ?object
    {
        $reports_table = \IAWP\Query::get_table_name(\IAWP\Query::REPORTS);
        return \IAWP\Illuminate_Builder::new()->from($reports_table)->where('report_id', '=', $id)->first();
    }
}
