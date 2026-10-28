<?php

namespace IAWP\Rows;

use IAWP\Form_Submissions\Form;
use IAWP\Illuminate_Builder;
use IAWP\Models\Referrer;
use IAWP\Query_Taps;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class Referrers extends \IAWP\Rows\Rows
{
    public function attach_filters(Builder $query) : void
    {
        $query->joinSub($this->query(\true), 'referrer_rows', function (JoinClause $join) {
            $join->on('referrer_rows.referrer_id', '=', 'sessions.referrer_id');
        });
    }
    protected function fetch_rows() : array
    {
        $rows = $this->query()->get()->all();
        return \array_map(function ($row) {
            return new Referrer($row);
        }, $rows);
    }
    protected function sort_tie_breaker_column() : string
    {
        return 'referrer';
    }
    private function query(?bool $skip_pagination = \false) : Builder
    {
        if ($skip_pagination) {
            $this->number_of_rows = null;
        }
        $orders_query = Illuminate_Builder::new()->select(['sessions.initial_view_id AS view_id'])->selectRaw('IFNULL(COUNT(DISTINCT orders.order_id), 0) AS wc_orders')->selectRaw('IFNULL(ROUND(CAST(SUM(orders.total) AS SIGNED)), 0) AS wc_gross_sales')->selectRaw('IFNULL(ROUND(CAST(SUM(orders.total_refunded) AS SIGNED)), 0) AS wc_refunded_amount')->selectRaw('IFNULL(SUM(orders.total_refunds), 0) AS wc_refunds')->from(Tables::orders(), 'orders')->leftJoin(Tables::views() . ' AS views', 'orders.view_id', '=', 'views.id')->leftJoin(Tables::sessions() . ' AS sessions', 'views.session_id', '=', 'sessions.session_id')->where('orders.is_included_in_analytics', '=', \true)->whereBetween('orders.created_at', $this->get_current_period_iso_range())->groupBy('orders.view_id');
        $session_statistics = Illuminate_Builder::new()->select('sessions.*')->selectRaw('COUNT(DISTINCT views.id) AS views')->selectRaw('COUNT(DISTINCT clicks.click_id) AS clicks')->selectRaw('IFNULL(SUM(orders.wc_orders), 0) AS wc_orders')->selectRaw('IFNULL(SUM(orders.wc_gross_sales), 0) AS wc_gross_sales')->selectRaw('IFNULL(SUM(orders.wc_refunded_amount), 0) AS wc_refunded_amount')->selectRaw('IFNULL(SUM(wc_refunds), 0) AS wc_refunds')->selectRaw('IFNULL(SUM(form_submissions.form_submissions), 0) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("SUM(IF(form_submissions.form_id = ?, form_submissions.form_submissions, 0)) AS {$form->submissions_column()}", [$form->id()]);
            }
        })->from(Tables::sessions(), 'sessions')->join(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->leftJoinSub($orders_query, 'orders', 'orders.view_id', '=', 'views.id')->leftJoin(Tables::clicks() . ' AS clicks', 'clicks.view_id', '=', 'views.id')->leftJoinSub($this->get_form_submissions_query(), 'form_submissions', 'form_submissions.view_id', '=', 'views.id')->tap(Query_Taps::tap_authored_content_check())->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config))->when(!$this->appears_to_be_for_real_time_analytics(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->whereNotNull('sessions.referrer_id')->groupBy('sessions.session_id');
        $referrers_query = Illuminate_Builder::new()->select('sessions.referrer_id', 'referrer', 'domain', 'referrer_types.id AS referrer_type_id', 'referrer_types.referrer_type AS referrer_type')->selectRaw('IFNULL(CAST(SUM(sessions.views) AS SIGNED), 0) AS views')->selectRaw('COUNT(DISTINCT sessions.visitor_id)  AS visitors')->selectRaw('COUNT(DISTINCT sessions.session_id)  AS sessions')->selectRaw('ROUND(AVG( TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at))) AS average_session_duration')->selectRaw('COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL))  AS bounces')->selectRaw('SUM(sessions.clicks)  AS clicks')->selectRaw('SUM(sessions.wc_orders) AS wc_orders')->selectRaw('SUM(sessions.wc_gross_sales) AS wc_gross_sales')->selectRaw('SUM(sessions.wc_refunded_amount) AS wc_refunded_amount')->selectRaw('SUM(sessions.wc_refunds) AS wc_refunds')->selectRaw('SUM(sessions.form_submissions) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("SUM(sessions.{$form->submissions_column()}) AS {$form->submissions_column()}");
            }
        })->fromSub($session_statistics, 'sessions')->leftJoin(Tables::referrers() . ' AS referrers', 'sessions.referrer_id', '=', 'referrers.id')->leftJoin(Tables::referrer_types() . ' AS referrer_types', 'referrers.referrer_type_id', '=', 'referrer_types.id')->when(!$this->appears_to_be_for_real_time_analytics(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->when(\is_int($this->solo_record_id), function (Builder $query) {
            $query->where('sessions.referrer_id', '=', $this->solo_record_id);
        })->groupBy('sessions.referrer_id')->having('views', '>', 0)->tap(fn(Builder $query) => $this->apply_record_filters($query))->when($this->can_order_and_limit_at_record_level(), function (Builder $query) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        });
        $previous_period_query = Illuminate_Builder::new()->select(['sessions.referrer_id'])->selectRaw('SUM(sessions.total_views) AS previous_period_views')->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS previous_period_visitors')->from(Tables::sessions(), 'sessions')->tap(Query_Taps::tap_related_to_examined_record_for_previous_period($this->examiner_config, ['sessions']))->whereBetween('sessions.created_at', $this->get_previous_period_iso_range())->groupBy('sessions.referrer_id');
        $outer_query = Illuminate_Builder::new()->selectRaw('referrers.*')->selectRaw('IF(sessions = 0, 0, views / sessions) AS views_per_session')->selectRaw('IFNULL((views - previous_period_views) / previous_period_views * 100, 0) AS views_growth')->selectRaw('IFNULL((visitors - previous_period_visitors) / previous_period_visitors * 100, 0) AS visitors_growth')->selectRaw('IFNULL(bounces / sessions * 100, 0) AS bounce_rate')->selectRaw('ROUND(CAST(wc_gross_sales - wc_refunded_amount AS SIGNED)) AS wc_net_sales')->selectRaw('IF(visitors = 0, 0, (wc_orders / visitors) * 100) AS wc_conversion_rate')->selectRaw('IF(visitors = 0, 0, (wc_gross_sales - wc_refunded_amount) / visitors) AS wc_earnings_per_visitor')->selectRaw('IF(wc_orders = 0, 0, ROUND(CAST(wc_gross_sales / wc_orders AS SIGNED))) AS wc_average_order_volume')->selectRaw('IF(visitors = 0, 0, (form_submissions / visitors) * 100) AS form_conversion_rate')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("IF(visitors = 0, 0, ({$form->submissions_column()} / visitors) * 100) AS {$form->conversion_rate_column()}");
            }
        })->tap(fn(Builder $query) => $this->apply_aggregate_filters($query))->fromSub($referrers_query, 'referrers')->leftJoinSub($previous_period_query, 'previous_period_stats', 'referrers.referrer_id', '=', 'previous_period_stats.referrer_id')->tap(fn(Builder $query) => $this->apply_aggregate_filters($query))->when(!$this->can_order_and_limit_at_record_level() && !($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()), function (Builder $query) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        });
        if ($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()) {
            $og_outer_query = $outer_query;
            $outer_query = Illuminate_Builder::new()->select('*')->fromSub($og_outer_query, 'records')->tap(fn(Builder $query) => $this->apply_or_filters($query))->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        }
        return $outer_query;
    }
}
