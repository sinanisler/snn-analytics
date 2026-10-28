<?php

namespace IAWP\Statistics;

use DateInterval;
use DatePeriod;
use DateTime;
use IAWP\Date_Range\Date_Range;
use IAWP\Form_Submissions\Form;
use IAWP\Illuminate_Builder;
use IAWP\Plugin_Group;
use IAWP\Query;
use IAWP\Query_Taps;
use IAWP\Rows\Rows;
use IAWP\Statistics\Intervals\Interval;
use IAWP\Statistics\Intervals\Intervals;
use IAWP\Tables;
use IAWP\Utils\Calculations;
use IAWP\Utils\CSV;
use IAWP\Utils\Timezone;
use IAWP\Utils\WordPress_Site_Date_Format_Pattern;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
use IAWPSCOPED\Illuminate\Support\Collection;
use IAWPSCOPED\Illuminate\Support\Str;
use Throwable;
/** @internal */
abstract class Statistics
{
    protected $tables = Tables::class;
    protected $date_range;
    protected $rows;
    protected $chart_interval;
    private $statistics;
    private $previous_period_statistics;
    private $statistics_grouped_by_date_interval;
    private $unfiltered_statistics;
    private $previous_period_unfiltered_statistic;
    private $unfiltered_statistics_grouped_by_date_interval;
    private $statistic_instances;
    // The biggest flaw here is that it requires two queries for the stats (current and previous) when
    // that could be one. I think it would also be possible to reuse the rows query and not limit
    // by 50 and just SUM() up all the stats columns for the quick stats. Maybe that would be faster
    // even if two queries were still used. Needs testing.
    public function __construct(Date_Range $date_range, ?Rows $rows = null, ?Interval $chart_interval = null)
    {
        $this->date_range = $date_range;
        $this->rows = $rows;
        $this->chart_interval = $chart_interval ?? Intervals::default_for($date_range->number_of_days());
        if ($rows instanceof Rows) {
            $this->statistics = $this->query($this->date_range, $rows);
            $this->previous_period_statistics = $this->query($this->date_range->previous_period(), $rows);
            $this->statistics_grouped_by_date_interval = $this->query($this->date_range, $rows, \true);
            $this->unfiltered_statistics = $this->query($this->date_range);
            $this->previous_period_unfiltered_statistic = $this->query($this->date_range->previous_period());
            $this->unfiltered_statistics_grouped_by_date_interval = $this->query($this->date_range, null, \true);
        } else {
            $this->statistics = $this->query($this->date_range);
            $this->previous_period_statistics = $this->query($this->date_range->previous_period());
            $this->statistics_grouped_by_date_interval = $this->query($this->date_range, null, \true);
        }
        $this->statistic_instances = $this->make_statistic_instances();
        $this->statistic_instances = \array_filter($this->statistic_instances, function (\IAWP\Statistics\Statistic $statistic) {
            return $statistic->is_enabled();
        });
    }
    /**
     * @return Statistic[]
     */
    public function get_statistics() : array
    {
        return Collection::make($this->statistic_instances)->filter(fn(\IAWP\Statistics\Statistic $statistic) => !$statistic->is_invisible())->values()->all();
    }
    public function get_grouped_statistics()
    {
        // This whole thing is a bit of a mess...
        return Collection::make($this->statistic_instances)->filter(fn(\IAWP\Statistics\Statistic $statistic) => !$statistic->is_invisible())->groupBy(function (\IAWP\Statistics\Statistic $item, int $key) {
            return Plugin_Group::get_plugin_group($item->plugin_group())->name();
        })->map(function (Collection $group, $plugin_group) {
            $items = $group->map(function (\IAWP\Statistics\Statistic $item) {
                if (!$item->is_group_plugin_enabled()) {
                    return null;
                }
                return ['id' => $item->id(), 'name' => $item->name()];
            })->filter();
            if ($items->isEmpty()) {
                return null;
            }
            return ['name' => $plugin_group, 'items' => $items->toArray()];
        })->filter()->values()->toArray();
    }
    /**
     * @param string $statistic_id
     * @param string ...$fallbacks A series of statistics ids to fall back to
     *
     * @return Statistic|null
     */
    public function get_statistic(string $statistic_id, string ...$fallbacks) : ?\IAWP\Statistics\Statistic
    {
        $statistics = Collection::make($this->statistic_instances);
        // First, try the primary statistic id
        $statistic = $statistics->first(function (\IAWP\Statistics\Statistic $statistic) use($statistic_id) {
            return $statistic->id() === $statistic_id;
        });
        if ($statistic instanceof \IAWP\Statistics\Statistic) {
            return $statistic;
        }
        // Second, try the fallback statistic ids
        foreach ($fallbacks as $fallback_statistic_id) {
            $statistic = $statistics->first(function (\IAWP\Statistics\Statistic $statistic) use($fallback_statistic_id) {
                return $statistic->id() === $fallback_statistic_id;
            });
            if ($statistic instanceof \IAWP\Statistics\Statistic) {
                return $statistic;
            }
        }
        return null;
    }
    public function has_filters() : bool
    {
        return !\is_null($this->rows);
    }
    public function chart_interval() : Interval
    {
        return $this->chart_interval;
    }
    /**
     * I'm sure there's more we could do here. If you get a result back where there isn't a full
     * page of results or where you're not paginating, then you can just count up the rows...
     *
     * @return int|null
     */
    public function total_number_of_rows() : ?int
    {
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $views_table = Query::get_table_name(Query::VIEWS);
        $referrers_table = Query::get_table_name(Query::REFERRERS);
        $campaigns_table = Query::get_table_name(Query::CAMPAIGNS);
        $column = $this->total_table_rows_column() ?? $this->required_column();
        $query = Illuminate_Builder::new()->selectRaw("COUNT(DISTINCT {$column}) AS total_table_rows")->from("{$sessions_table} AS sessions")->join("{$views_table} AS views", function (JoinClause $join) {
            $join->on('sessions.session_id', '=', 'views.session_id');
        })->leftJoin("{$referrers_table} AS referrers", function (JoinClause $join) {
            $join->on('sessions.referrer_id', '=', 'referrers.id');
        })->leftJoin("{$campaigns_table} AS campaigns", function (JoinClause $join) {
            $join->on('sessions.campaign_id', '=', 'campaigns.campaign_id');
        })->tap(Query_Taps::tap_authored_content_check(\true))->when(!\is_null($this->rows), function (Builder $query) {
            $this->rows->attach_filters($query);
        })->whereBetween('sessions.created_at', [$this->date_range->iso_start(), $this->date_range->iso_end()])->whereBetween('views.viewed_at', [$this->date_range->iso_start(), $this->date_range->iso_end()]);
        return $query->value('total_table_rows');
    }
    public function get_statistics_as_csv() : CSV
    {
        $statistics = \array_values(\array_filter($this->get_statistics(), function (\IAWP\Statistics\Statistic $statistic) {
            return $statistic->is_visible() && $statistic->is_enabled() && $statistic->is_group_plugin_enabled();
        }));
        $csv_header = [];
        $csv_rows = [];
        // The first column is the date interval label. There's no title for this column.
        $csv_header[] = '';
        foreach ($statistics as $statistic) {
            $csv_header[] = $statistic->name();
        }
        $total_rows = \count($statistics[0]->statistic_over_time());
        for ($i = 0; $i < $total_rows; $i++) {
            $csv_row = [];
            // The first row is the date interval label
            $csv_row[] = $statistics[0]->statistic_over_time()[$i][0]->format(WordPress_Site_Date_Format_Pattern::for_php());
            foreach ($statistics as $statistic) {
                $csv_row[] = $statistic->format_value($statistic->statistic_over_time()[$i][1]);
            }
            $csv_rows[] = $csv_row;
        }
        // Use the summary row to show statistic totals
        $summary_row = ['Total'];
        foreach ($statistics as $index => $statistic) {
            $summary_row[$index + 1] = $statistic->formatted_value();
        }
        // Add summary row to the top
        \array_unshift($csv_rows, $summary_row);
        $csv = new CSV($csv_header, $csv_rows);
        return $csv;
    }
    /**
     * Define which id column to use to count up the total table rows. This is only required
     * for classes that don't have a required column and don't override required_column
     *
     * @return string|null
     */
    protected function total_table_rows_column() : ?string
    {
        return null;
    }
    /**
     * Statistics can require that a column exists in order to be included. As an example, geos
     * requires visitors.country_code and campaigns requires sessions.campaign_id
     *
     * @return string|null
     */
    protected function required_column() : ?string
    {
        return null;
    }
    /**
     * @return Statistic[]
     */
    protected function make_statistic_instances() : array
    {
        $statistics = [$this->make_statistic(['id' => 'visitors', 'name' => \__('Visitors', 'independent-analytics'), 'plugin_group' => 'general', 'is_visible_in_dashboard_widget' => \true]), $this->make_statistic(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'plugin_group' => 'general', 'is_visible_in_dashboard_widget' => \true]), $this->make_statistic(['id' => 'sessions', 'name' => \__('Sessions', 'independent-analytics'), 'plugin_group' => 'general']), $this->make_statistic(['id' => 'average_session_duration', 'name' => \__('Average Session Duration', 'independent-analytics'), 'plugin_group' => 'general', 'format' => 'time']), $this->make_statistic(['id' => 'bounce_rate', 'name' => \__('Bounce Rate', 'independent-analytics'), 'plugin_group' => 'general', 'format' => 'percent', 'is_growth_good' => \false, 'compute' => function (object $statistics) {
            return Calculations::percentage($statistics->bounces, $statistics->sessions);
        }]), $this->make_statistic(['id' => 'views_per_session', 'name' => \__('Views Per Session', 'independent-analytics'), 'plugin_group' => 'general', 'format' => 'decimal', 'compute' => function (object $statistics) {
            return Calculations::divide($statistics->total_views, $statistics->sessions, 2);
        }]), $this->make_statistic(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'plugin_group' => 'general', 'requires_pro' => \true]), $this->make_statistic(['id' => 'wc_orders', 'name' => \__('Orders', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon()]), $this->make_statistic(['id' => 'wc_gross_sales', 'name' => \__('Gross Sales', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon(), 'format' => 'rounded-currency']), $this->make_statistic(['id' => 'wc_refunds', 'name' => \__('Refunds', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon()]), $this->make_statistic(['id' => 'wc_refunded_amount', 'name' => \__('Refunded Amount', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon(), 'format' => 'rounded-currency']), $this->make_statistic(['id' => 'wc_net_sales', 'name' => \__('Total Sales', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon(), 'format' => 'rounded-currency']), $this->make_statistic(['id' => 'wc_conversion_rate', 'name' => \__('Conversion Rate', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon(), 'format' => 'percent']), $this->make_statistic(['id' => 'wc_earnings_per_visitor', 'name' => \__('Earnings Per Visitor', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon(), 'format' => 'currency']), $this->make_statistic(['id' => 'wc_average_order_volume', 'name' => \__('Average Order Volume', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'icon' => $this->get_ecommerce_icon(), 'format' => 'rounded-currency']), $this->make_statistic(['id' => 'form_submissions', 'name' => \__('Form Submissions', 'independent-analytics'), 'plugin_group' => 'forms']), $this->make_statistic(['id' => 'form_conversion_rate', 'name' => \__('Form Conversion Rate', 'independent-analytics'), 'plugin_group' => 'forms', 'format' => 'percent', 'compute' => function (object $statistics) {
            return Calculations::percentage($statistics->form_submissions, $statistics->visitors, 2);
        }])];
        foreach (Form::get_forms() as $form) {
            if (!$form->is_plugin_active()) {
                continue;
            }
            $statistics[] = $this->make_statistic(['id' => 'form_submissions_for_' . $form->id(), 'name' => \sprintf(\_x('%s Submissions', 'Title of the contact form', 'independent-analytics'), $form->title()), 'plugin_group' => 'forms', 'is_subgroup_plugin_active' => $form->is_plugin_active(), 'plugin_group_header' => $form->plugin_name(), 'icon' => $form->icon()]);
            $statistics[] = $this->make_statistic(['id' => 'form_conversion_rate_for_' . $form->id(), 'name' => \sprintf(\_x('%s Conversion Rate', 'Title of the contact form', 'independent-analytics'), $form->title()), 'plugin_group' => 'forms', 'is_subgroup_plugin_active' => $form->is_plugin_active(), 'plugin_group_header' => $form->plugin_name(), 'icon' => $form->icon(), 'format' => 'percent', 'compute' => function (object $statistics) use($form) {
                $form_submission_id = 'form_submissions_for_' . $form->id();
                return Calculations::percentage($statistics->{$form_submission_id}, $statistics->visitors, 2);
            }]);
        }
        return $statistics;
    }
    protected function make_statistic(array $attributes) : \IAWP\Statistics\Statistic
    {
        $statistic_id = $attributes['id'];
        if (!\array_key_exists('compute', $attributes)) {
            $attributes['compute'] = function ($statistics, $statistic_id) {
                return $statistics->{$statistic_id};
            };
        }
        $attributes['statistic'] = $attributes['compute']($this->statistics, $statistic_id);
        $attributes['previous_period_statistic'] = $attributes['compute']($this->previous_period_statistics, $statistic_id);
        $attributes['statistic_over_time'] = $this->fill_in_partial_day_range($this->statistics_grouped_by_date_interval, $attributes);
        $attributes['unfiltered_statistic'] = $this->has_filters() ? $attributes['compute']($this->unfiltered_statistics, $statistic_id) : null;
        return new \IAWP\Statistics\Statistic($attributes);
    }
    protected function query(Date_Range $range, ?Rows $rows = null, bool $is_grouped_by_date_interval = \false)
    {
        $utc_offset = Timezone::utc_offset();
        $site_offset = Timezone::site_offset();
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $views_table = Query::get_table_name(Query::VIEWS);
        $orders_table = Query::get_table_name(Query::ORDERS);
        $referrers_table = Query::get_table_name(Query::REFERRERS);
        $campaigns_table = Query::get_table_name(Query::CAMPAIGNS);
        $form_submissions_table = Query::get_table_name(Query::FORM_SUBMISSIONS);
        $form_submissions_query = Illuminate_Builder::new()->select(['form_id', 'view_id'])->selectRaw('COUNT(*) AS form_submissions')->from($form_submissions_table, 'form_submissions')->whereBetween('created_at', [$range->iso_start(), $range->iso_end()])->groupBy(['form_id', 'view_id']);
        $session_statistics = Illuminate_Builder::new();
        $session_statistics->select('sessions.session_id')->selectRaw('COUNT(DISTINCT views.id) AS views')->selectRaw('COUNT(DISTINCT clicks.click_id) AS clicks')->selectRaw('COUNT(DISTINCT orders.order_id) AS orders')->selectRaw('IFNULL(CAST(SUM(orders.total) AS SIGNED), 0) AS gross_sales')->selectRaw('IFNULL(CAST(SUM(orders.total_refunded) AS SIGNED), 0) AS total_refunded')->selectRaw('IFNULL(CAST(SUM(orders.total_refunds) AS SIGNED), 0) AS total_refunds')->selectRaw('IFNULL(CAST(SUM(orders.total - orders.total_refunded) AS SIGNED), 0) AS net_sales')->selectRaw('IFNULL(SUM(form_submissions.form_submissions), 0) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw('IFNULL(SUM(IF(form_submissions.form_id = ?, form_submissions.form_submissions, 0)), 0) AS ' . $form->submissions_column(), [$form->id()]);
            }
        })->from("{$sessions_table} AS sessions")->join("{$views_table} AS views", function (JoinClause $join) {
            $join->on('sessions.session_id', '=', 'views.session_id');
        })->leftJoin("{$orders_table} AS orders", function (JoinClause $join) {
            $join->on('views.id', '=', 'orders.initial_view_id')->where('orders.is_included_in_analytics', '=', \true);
        })->leftJoin("{$this->tables::clicks()} AS clicks", function (JoinClause $join) {
            $join->on('views.id', '=', 'clicks.view_id');
        })->leftJoin("{$referrers_table} AS referrers", function (JoinClause $join) {
            $join->on('sessions.referrer_id', '=', 'referrers.id');
        })->leftJoin("{$campaigns_table} AS campaigns", function (JoinClause $join) {
            $join->on('sessions.campaign_id', '=', 'campaigns.campaign_id');
        })->leftJoinSub($form_submissions_query, 'form_submissions', 'views.id', '=', 'form_submissions.view_id')->tap(Query_Taps::tap_authored_content_check(\true))->when(!\is_null($rows), function (Builder $query) use($rows) {
            $rows->attach_filters($query);
        })->whereBetween('sessions.created_at', [$range->iso_start(), $range->iso_end()])->whereBetween('views.viewed_at', [$range->iso_start(), $range->iso_end()])->groupBy('sessions.session_id')->when(!\is_null($this->required_column()), function (Builder $query) {
            $query->whereNotNull($this->required_column());
        });
        $statistics = Illuminate_Builder::new();
        $statistics->selectRaw('IFNULL(CAST(SUM(sessions.total_views) AS SIGNED), 0) AS total_views')->selectRaw('IFNULL(CAST(SUM(session_statistics.views) AS SIGNED), 0) AS views')->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS visitors')->selectRaw('COUNT(DISTINCT sessions.session_id) AS sessions')->selectRaw('IFNULL(CAST(SUM(session_statistics.clicks) AS SIGNED), 0) AS clicks')->selectRaw('IFNULL(CAST(AVG(TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at)) AS SIGNED), 0) AS average_session_duration')->selectRaw('COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL)) AS bounces')->selectRaw('IFNULL(CAST(SUM(session_statistics.orders) AS SIGNED), 0) AS wc_orders')->selectRaw('IFNULL(CAST(SUM(session_statistics.gross_sales) AS SIGNED), 0) AS wc_gross_sales')->selectRaw('IFNULL(CAST(SUM(session_statistics.total_refunds) AS SIGNED), 0) AS wc_refunds')->selectRaw('IFNULL(CAST(SUM(session_statistics.total_refunded) AS SIGNED), 0) AS wc_refunded_amount')->selectRaw('IFNULL(CAST(SUM(session_statistics.net_sales) AS SIGNED), 0) AS wc_net_sales')->selectRaw('IFNULL(SUM(session_statistics.form_submissions), 0) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw('IFNULL(SUM(session_statistics.' . $form->submissions_column() . '), 0) AS ' . $form->submissions_column());
            }
        })->from("{$sessions_table} AS sessions")->joinSub($session_statistics, 'session_statistics', function (JoinClause $join) {
            $join->on('sessions.session_id', '=', 'session_statistics.session_id');
        })->whereBetween('sessions.created_at', [$range->iso_start(), $range->iso_end()])->when($is_grouped_by_date_interval, function (Builder $query) use($utc_offset, $site_offset) {
            if ($this->chart_interval->id() === 'daily') {
                $query->selectRaw("DATE(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}')) AS date");
            } elseif ($this->chart_interval->id() === 'monthly') {
                $query->selectRaw("DATE_FORMAT(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}'), '%Y-%m-01 00:00:00') AS date");
            } elseif ($this->chart_interval->id() === 'weekly') {
                $day_of_week = \IAWPSCOPED\iawp()->get_option('iawp_dow', 0) + 1;
                $query->selectRaw("\n                               IF (\n                                  DAYOFWEEK(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}')) - {$day_of_week} < 0,\n                                  DATE_FORMAT(SUBDATE(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}'), DAYOFWEEK(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}')) - {$day_of_week} + 7), '%Y-%m-%d 00:00:00'),\n                                  DATE_FORMAT(SUBDATE(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}'), DAYOFWEEK(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}')) - {$day_of_week}), '%Y-%m-%d 00:00:00')\n                               ) AS date\n                           ");
            } else {
                $query->selectRaw("DATE_FORMAT(CONVERT_TZ(sessions.created_at, '{$utc_offset}', '{$site_offset}'), '%Y-%m-%d %H:00:00') AS date");
            }
            $query->groupByRaw("date");
        });
        $outer_query = Illuminate_Builder::new()->selectRaw('statistics.*')->selectRaw('IF(statistics.visitors = 0, 0, (statistics.wc_orders / statistics.visitors) * 100) AS wc_conversion_rate')->selectRaw('IF(statistics.visitors = 0, 0, (statistics.wc_gross_sales - statistics.wc_refunded_amount) / visitors) AS wc_earnings_per_visitor')->selectRaw('IF(statistics.wc_orders = 0, 0, ROUND(CAST(statistics.wc_gross_sales / statistics.wc_orders AS DECIMAL(10, 2)))) AS wc_average_order_volume')->fromSub($statistics, 'statistics');
        $results = \array_map(function (object $statistic) : object {
            return $this->clean_up_raw_statistic_row($statistic);
        }, $outer_query->get()->all());
        if (!$is_grouped_by_date_interval) {
            return $results[0];
        }
        return $results;
    }
    protected function clean_up_raw_statistic_row(object $statistic) : object
    {
        if (\property_exists($statistic, 'wc_gross_sales')) {
            $statistic->wc_gross_sales = \intval($statistic->wc_gross_sales);
        }
        if (\property_exists($statistic, 'wc_refunded_amount')) {
            $statistic->wc_refunded_amount = \intval($statistic->wc_refunded_amount);
        }
        if (\property_exists($statistic, 'wc_net_sales')) {
            $statistic->wc_net_sales = \intval($statistic->wc_net_sales);
        }
        if (\property_exists($statistic, 'wc_earnings_per_visitor')) {
            $statistic->wc_earnings_per_visitor = \intval($statistic->wc_earnings_per_visitor);
        }
        foreach ($statistic as $key => $value) {
            if (Str::startsWith($key, 'form_submissions')) {
                $statistic->{$key} = \intval($value);
            }
        }
        return $statistic;
    }
    /**
     * @param array  $partial_day_range
     * @param array $attributes
     *
     * @return array
     */
    private function fill_in_partial_day_range(array $partial_day_range, array $attributes) : array
    {
        $original_start = (clone $this->date_range->start())->setTimezone(Timezone::site_timezone());
        $start = $this->chart_interval->calculate_start_of_interval_for($original_start);
        $original_end = (clone $this->date_range->end())->setTimezone(Timezone::site_timezone());
        $end = $this->chart_interval->calculate_start_of_interval_for($original_end);
        $end->add(new DateInterval('PT1S'));
        $is_problematic_timezone = \in_array(Timezone::site_timezone()->getName(), ['Asia/Beirut', 'America/Santiago', 'America/Havana', 'America/Asuncion', 'Atlantic/Azores']);
        // Imagine a scenario where the offset for the start date is -4 and the offset for the
        // end date is -3. In that case, the DatePeriod is actually going to have its end date
        // short an hour, causing a date we care about to be chopped off the end. This code makes
        // sure that the end date is still the day after the last date we care about, so it
        // can get chopped off without consequence.
        if ($is_problematic_timezone) {
            $offset_difference = $end->getOffset() - $start->getOffset();
            $interval = DateInterval::createFromDateString($offset_difference . ' seconds');
            $end->add($interval);
        }
        $date_range = new DatePeriod($start, $this->chart_interval->date_interval(), $end);
        $filled_in_data = [];
        foreach ($date_range as $date) {
            // If the timezone switches at midnight, there will be a date where there is no midnight. It'll be something
            // like 1am instead. All dates after the day that switches will also be at 1am even though they have a
            // midnight. This alters those back to midnight, so they can be matched against correctly.
            if ($is_problematic_timezone && $date->format('H:i:s') === "01:00:00") {
                $date->setTime(0, 0, 0);
            }
            $stat = $this->get_statistic_for_date($partial_day_range, $date, $attributes);
            $filled_in_data[] = [$date, $stat];
        }
        return $filled_in_data;
    }
    /**
     * @param array    $partial_day_range
     * @param DateTime $datetime_to_match
     * @param array   $attributes
     *
     * @return float|int
     */
    private function get_statistic_for_date(array $partial_day_range, DateTime $datetime_to_match, array $attributes)
    {
        foreach ($partial_day_range as $day) {
            $date = $day->date;
            $value = $attributes['compute']($day, $attributes['id']);
            try {
                $datetime = new DateTime($date, Timezone::site_timezone());
            } catch (Throwable $e) {
                return 0;
            }
            // Intentionally using non-strict equality to see if two distinct DateTime objects represent the same time
            if ($datetime == $datetime_to_match) {
                if (\is_string($value)) {
                    if (\strpos($value, '.') !== \false) {
                        return \floatval($value);
                    } else {
                        return \intval($value);
                    }
                }
                return $value;
            }
        }
        return 0;
    }
    private function get_ecommerce_icon() : ?string
    {
        if (\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'woocommerce';
        }
        if (\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'surecart';
        }
        if (\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'edd';
        }
        if (\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'pmpro';
        }
        if (\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled()) {
            return 'fluentcart';
        }
        return null;
    }
}
