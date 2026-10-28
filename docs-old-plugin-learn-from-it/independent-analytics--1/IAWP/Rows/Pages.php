<?php

namespace IAWP\Rows;

use IAWP\Database;
use IAWP\Form_Submissions\Form;
use IAWP\Illuminate_Builder;
use IAWP\Models\Page;
use IAWP\Query_Taps;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class Pages extends \IAWP\Rows\Rows
{
    private static $has_wp_comments_table = null;
    public function attach_filters(Builder $query) : void
    {
        $query->joinSub($this->query(\true), 'page_rows', function (JoinClause $join) {
            $join->on('page_rows.id', '=', 'views.resource_id');
        });
    }
    protected function fetch_rows() : array
    {
        $rows = $this->query()->get()->all();
        return \array_map(function (object $row) {
            return Page::from_row($row);
        }, $rows);
    }
    protected function sort_tie_breaker_column() : string
    {
        return 'cached_title';
    }
    private function has_wp_comments_table() : bool
    {
        if (\is_bool(self::$has_wp_comments_table)) {
            return self::$has_wp_comments_table;
        }
        global $wpdb;
        self::$has_wp_comments_table = Database::has_table($wpdb->prefix . 'comments');
        return self::$has_wp_comments_table;
    }
    private function query(?bool $skip_pagination = \false) : Builder
    {
        if ($skip_pagination) {
            $this->number_of_rows = null;
        }
        $database_sort_columns = ['title' => 'cached_title', 'url' => 'cached_url', 'author' => 'cached_author', 'type' => 'cached_type_label', 'date' => 'cached_date', 'category' => 'cached_category'];
        $sort_column = $this->sort_configuration->column();
        foreach ($database_sort_columns as $key => $value) {
            if ($sort_column === $key) {
                $sort_column = $value;
            }
        }
        $orders_query = Illuminate_Builder::new()->select(['sessions.initial_view_id AS view_id'])->selectRaw('IFNULL(COUNT(DISTINCT orders.order_id), 0) AS wc_orders')->selectRaw('IFNULL(ROUND(CAST(SUM(orders.total) AS SIGNED)), 0) AS wc_gross_sales')->selectRaw('IFNULL(ROUND(CAST(SUM(orders.total_refunded) AS SIGNED)), 0) AS wc_refunded_amount')->selectRaw('IFNULL(SUM(orders.total_refunds), 0) AS wc_refunds')->from(Tables::orders(), 'orders')->leftJoin(Tables::views() . ' AS views', 'orders.view_id', '=', 'views.id')->leftJoin(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->where('orders.is_included_in_analytics', '=', \true)->whereBetween('orders.created_at', $this->get_current_period_iso_range())->groupBy('orders.view_id');
        $pages_query = Illuminate_Builder::new()->select('resources.*')->selectRaw('COUNT(DISTINCT views.id)  AS views')->selectRaw('COUNT(DISTINCT sessions.visitor_id)  AS visitors')->selectRaw('COUNT(DISTINCT IF(initial_view.resource_id = resources.id, sessions.visitor_id, NULL))  AS landing_page_visitors')->selectRaw('COUNT(DISTINCT sessions.session_id)  AS sessions')->selectRaw('COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL))  AS bounces')->selectRaw('AVG(TIMESTAMPDIFF(SECOND, views.viewed_at, views.next_viewed_at))  AS average_view_duration')->selectRaw('COUNT(DISTINCT IF(resources.id = initial_view.resource_id, sessions.session_id, NULL))  AS entrances')->selectRaw('COUNT(DISTINCT IF((resources.id = final_view.resource_id OR (resources.id = initial_view.resource_id AND sessions.final_view_id IS NULL)), sessions.session_id, NULL))  AS exits')->selectRaw('COUNT(DISTINCT clicks.click_id)  AS clicks')->selectRaw('IFNULL(SUM(the_orders.wc_orders), 0) AS wc_orders')->selectRaw('IFNULL(SUM(the_orders.wc_gross_sales), 0) AS wc_gross_sales')->selectRaw('IFNULL(SUM(the_orders.wc_refunded_amount), 0) AS wc_refunded_amount')->selectRaw('IFNULL(SUM(wc_refunds), 0) AS wc_refunds')->selectRaw('IFNULL(SUM(form_submissions.form_submissions), 0) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("SUM(IF(form_submissions.form_id = ?, form_submissions.form_submissions, 0)) AS {$form->submissions_column()}", [$form->id()]);
            }
        })->from(Tables::views(), 'views')->leftJoin(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->leftJoin(Tables::resources() . ' AS resources', 'views.resource_id', '=', 'resources.id')->leftJoin(Tables::views() . ' AS initial_view', 'sessions.initial_view_id', '=', 'initial_view.id')->leftJoin(Tables::views() . ' AS final_view', 'sessions.final_view_id', '=', 'final_view.id')->leftJoin(Tables::clicks() . ' AS clicks', 'clicks.view_id', '=', 'views.id')->leftJoinSub($orders_query, 'the_orders', 'the_orders.view_id', '=', 'views.id')->leftJoinSub($this->get_form_submissions_query(), 'form_submissions', 'form_submissions.view_id', '=', 'views.id')->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config))->tap(Query_Taps::tap_authored_content_check(\false))->when($this->has_wp_comments_table(), function (Builder $query) {
            $query->selectRaw('IFNULL(comments.comments, 0) AS comments');
            $query->leftJoinSub($this->get_comments_query(), 'comments', 'comments.resource_id', '=', 'resources.id');
        }, function (Builder $query) {
            $query->selectRaw('0 AS comments');
        })->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->when(!$this->appears_to_be_for_real_time_analytics(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->tap(fn(Builder $query) => $this->remove_non_singulars_when_filtering_by_comments($query))->when(\is_int($this->solo_record_id), function (Builder $query) {
            $query->where('resources.id', '=', $this->solo_record_id);
        })->groupBy('resources.id')->having('views', '>', 0)->tap(fn(Builder $query) => $this->apply_record_filters($query))->when($this->can_order_and_limit_at_record_level(), function (Builder $query) use($sort_column) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $sort_column));
        });
        $previous_period_query = Illuminate_Builder::new()->select(['views.resource_id'])->selectRaw('COUNT(*) AS previous_period_views')->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS previous_period_visitors')->from(Tables::views(), 'views')->leftJoin(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->tap(Query_Taps::tap_related_to_examined_record_for_previous_period($this->examiner_config, ['views', 'sessions']))->whereBetween('views.viewed_at', $this->get_previous_period_iso_range())->whereBetween('sessions.created_at', $this->get_previous_period_iso_range())->groupBy('views.resource_id');
        $outer_query = Illuminate_Builder::new()->selectRaw('pages.*')->selectRaw('IFNULL((views - previous_period_views) / previous_period_views * 100, 0) AS views_growth')->selectRaw('IFNULL((visitors - previous_period_visitors) / previous_period_visitors * 100, 0) AS visitors_growth')->selectRaw('IFNULL(bounces / sessions * 100, 0) AS bounce_rate')->selectRaw('IFNULL((exits / views) * 100, 0) AS exit_percent')->selectRaw('ROUND(CAST(wc_gross_sales - wc_refunded_amount AS SIGNED)) AS wc_net_sales')->selectRaw('IF(visitors = 0, 0, (wc_orders / landing_page_visitors) * 100) AS wc_conversion_rate')->selectRaw('IF(visitors = 0, 0, (wc_gross_sales - wc_refunded_amount) / landing_page_visitors) AS wc_earnings_per_visitor')->selectRaw('IF(wc_orders = 0, 0, ROUND(CAST(wc_gross_sales / wc_orders AS SIGNED))) AS wc_average_order_volume')->selectRaw('IF(visitors = 0, 0, (form_submissions / visitors) * 100) AS form_conversion_rate')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("IF(visitors = 0, 0, ({$form->submissions_column()} / visitors) * 100) AS {$form->conversion_rate_column()}");
            }
        })->fromSub($pages_query, 'pages')->leftJoinSub($previous_period_query, 'previous_period_stats', 'pages.id', '=', 'previous_period_stats.resource_id')->tap(fn(Builder $query) => $this->apply_aggregate_filters($query))->when(!$this->can_order_and_limit_at_record_level() && !($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()), function (Builder $query) use($sort_column) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $sort_column));
        });
        if ($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()) {
            $og_outer_query = $outer_query;
            $outer_query = Illuminate_Builder::new()->select('*')->fromSub($og_outer_query, 'records')->tap(fn(Builder $query) => $this->apply_or_filters($query))->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $sort_column));
        }
        return $outer_query;
    }
    private function get_comments_query() : Builder
    {
        global $wpdb;
        $comments_table = $wpdb->prefix . 'comments';
        $comments_query = Illuminate_Builder::new()->select(['resources.id AS resource_id'])->selectRaw('COUNT(*) AS comments')->from($comments_table, 'comments')->join(Tables::resources() . ' AS resources', 'comments.comment_post_ID', '=', 'resources.singular_id')->where('comments.comment_type', '=', 'comment')->where('comments.comment_approved', '=', '1')->whereBetween('comments.comment_date_gmt', $this->get_current_period_iso_range())->groupBy('resources.id');
        return $comments_query;
    }
    private function remove_non_singulars_when_filtering_by_comments(Builder $query) : void
    {
        foreach ($this->filters as $filter) {
            if ($filter->column() !== 'comments') {
                continue;
            }
            $query->whereNotNull('singular_id');
            break;
        }
    }
}
