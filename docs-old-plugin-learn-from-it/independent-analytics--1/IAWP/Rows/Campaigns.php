<?php

namespace IAWP\Rows;

use IAWP\Form_Submissions\Form;
use IAWP\Illuminate_Builder;
use IAWP\Models\Campaign;
use IAWP\Query;
use IAWP\Query_Taps;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class Campaigns extends \IAWP\Rows\Rows
{
    public function attach_filters(Builder $query) : void
    {
        $query->joinSub($this->query(\true), 'campaign_rows', function (JoinClause $join) {
            $join->on('campaign_rows.campaign_id', '=', 'sessions.campaign_id');
        });
    }
    protected function fetch_rows() : array
    {
        $rows = $this->query()->get()->all();
        return \array_map(function ($row) {
            return new Campaign($row);
        }, $rows);
    }
    protected function sort_tie_breaker_column() : string
    {
        return 'title';
    }
    private function query(?bool $skip_pagination = \false) : Builder
    {
        if ($skip_pagination) {
            $this->number_of_rows = null;
        }
        $views_table = Query::get_table_name(Query::VIEWS);
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $orders_table = Query::get_table_name(Query::ORDERS);
        $campaigns_table = Query::get_table_name(Query::CAMPAIGNS);
        $session_statistics = Illuminate_Builder::new();
        $session_statistics->select('sessions.*')->selectRaw('COUNT(DISTINCT views.id) AS views')->selectRaw('COUNT(DISTINCT clicks.click_id) AS clicks')->selectRaw('COUNT(DISTINCT orders.order_id) AS wc_orders')->selectRaw('IFNULL(CAST(SUM(orders.total) AS SIGNED), 0) AS wc_gross_sales')->selectRaw('IFNULL(CAST(SUM(orders.total_refunded) AS SIGNED), 0) AS wc_refunded_amount')->selectRaw('IFNULL(CAST(SUM(orders.total_refunds) AS SIGNED), 0) AS wc_refunds')->selectRaw('IFNULL(SUM(form_submissions.form_submissions), 0) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("SUM(IF(form_submissions.form_id = ?, form_submissions.form_submissions, 0)) AS {$form->submissions_column()}", [$form->id()]);
            }
        })->from("{$sessions_table} AS sessions")->join("{$views_table} AS views", function (JoinClause $join) {
            $join->on('sessions.session_id', '=', 'views.session_id');
        })->leftJoin("{$orders_table} AS orders", function (JoinClause $join) {
            $join->on('views.id', '=', 'orders.initial_view_id')->where('orders.is_included_in_analytics', '=', \true);
        })->leftJoin("{$this->tables::clicks()} AS clicks", function (JoinClause $join) {
            $join->on('views.id', '=', 'clicks.view_id');
        })->tap(Query_Taps::tap_authored_content_check())->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config))->when(!$this->appears_to_be_for_real_time_analytics(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->whereBetween('views.viewed_at', $this->get_current_period_iso_range())->leftJoinSub($this->get_form_submissions_query(), 'form_submissions', function (JoinClause $join) {
            $join->on('form_submissions.view_id', '=', 'views.id');
        })->whereNotNull('sessions.campaign_id')->groupBy('sessions.session_id');
        $campaigns_query = Illuminate_Builder::new();
        $campaigns_query->select('campaigns.campaign_id', 'landing_pages.title', 'utm_sources.utm_source', 'utm_mediums.utm_medium', 'utm_campaigns.utm_campaign', 'utm_term', 'utm_content')->selectRaw('IFNULL(CAST(SUM(sessions.views) AS SIGNED), 0) AS views')->selectRaw('COUNT(DISTINCT sessions.visitor_id)  AS visitors')->selectRaw('COUNT(DISTINCT sessions.session_id)  AS sessions')->selectRaw('ROUND(AVG( TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at))) AS average_session_duration')->selectRaw('COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL))  AS bounces')->selectRaw('SUM(sessions.clicks)  AS clicks')->selectRaw('SUM(sessions.wc_orders) AS wc_orders')->selectRaw('SUM(sessions.wc_gross_sales) AS wc_gross_sales')->selectRaw('SUM(sessions.wc_refunded_amount) AS wc_refunded_amount')->selectRaw('SUM(sessions.wc_refunds) AS wc_refunds')->selectRaw('SUM(sessions.form_submissions) AS form_submissions')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("SUM(sessions.{$form->submissions_column()}) AS {$form->submissions_column()}");
            }
        })->fromSub($session_statistics, 'sessions')->join($campaigns_query->raw($campaigns_table . ' AS campaigns'), function (JoinClause $join) {
            $join->on('sessions.campaign_id', '=', 'campaigns.campaign_id');
        })->join($campaigns_query->raw(Tables::landing_pages() . ' AS landing_pages'), function (JoinClause $join) {
            $join->on('campaigns.landing_page_id', '=', 'landing_pages.id');
        })->join($campaigns_query->raw(Tables::utm_sources() . ' AS utm_sources'), function (JoinClause $join) {
            $join->on('campaigns.utm_source_id', '=', 'utm_sources.id');
        })->join($campaigns_query->raw(Tables::utm_mediums() . ' AS utm_mediums'), function (JoinClause $join) {
            $join->on('campaigns.utm_medium_id', '=', 'utm_mediums.id');
        })->join($campaigns_query->raw(Tables::utm_campaigns() . ' AS utm_campaigns'), function (JoinClause $join) {
            $join->on('campaigns.utm_campaign_id', '=', 'utm_campaigns.id');
        })->when(!$this->appears_to_be_for_real_time_analytics(), function (Builder $query) {
            $query->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        })->when(\is_int($this->solo_record_id), function (Builder $query) {
            $query->where('campaigns.campaign_id', '=', $this->solo_record_id);
        })->groupBy('campaigns.campaign_id')->having('views', '>', 0)->tap(fn(Builder $query) => $this->apply_record_filters($query))->when($this->can_order_and_limit_at_record_level(), function (Builder $query) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        });
        $previous_period_query = Illuminate_Builder::new();
        $previous_period_query->select(['sessions.campaign_id'])->selectRaw('SUM(sessions.total_views) AS previous_period_views')->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS previous_period_visitors')->from($sessions_table, 'sessions')->tap(Query_Taps::tap_related_to_examined_record_for_previous_period($this->examiner_config, ['sessions']))->whereBetween('sessions.created_at', $this->get_previous_period_iso_range())->groupBy('sessions.campaign_id');
        $outer_query = Illuminate_Builder::new();
        $outer_query->selectRaw('campaigns.*')->selectRaw('IF(sessions = 0, 0, views / sessions) AS views_per_session')->selectRaw('IFNULL((views - previous_period_views) / previous_period_views * 100, 0) AS views_growth')->selectRaw('IFNULL((visitors - previous_period_visitors) / previous_period_visitors * 100, 0) AS visitors_growth')->selectRaw('IFNULL(bounces / sessions * 100, 0) AS bounce_rate')->selectRaw('ROUND(CAST(wc_gross_sales - wc_refunded_amount AS SIGNED)) AS wc_net_sales')->selectRaw('IF(visitors = 0, 0, (wc_orders / visitors) * 100) AS wc_conversion_rate')->selectRaw('IF(visitors = 0, 0, (wc_gross_sales - wc_refunded_amount) / visitors) AS wc_earnings_per_visitor')->selectRaw('IF(wc_orders = 0, 0, ROUND(CAST(wc_gross_sales / wc_orders AS SIGNED))) AS wc_average_order_volume')->selectRaw('IF(visitors = 0, 0, (form_submissions / visitors) * 100) AS form_conversion_rate')->tap(function (Builder $query) {
            foreach (Form::get_forms() as $form) {
                $query->selectRaw("IF(visitors = 0, 0, ({$form->submissions_column()} / visitors) * 100) AS {$form->conversion_rate_column()}");
            }
        })->fromSub($campaigns_query, 'campaigns')->leftJoinSub($previous_period_query, 'previous_period_stats', 'campaigns.campaign_id', '=', 'previous_period_stats.campaign_id')->tap(fn(Builder $query) => $this->apply_aggregate_filters($query))->when(!$this->can_order_and_limit_at_record_level() && !($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()), function (Builder $query) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        });
        if ($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()) {
            $og_outer_query = $outer_query;
            $outer_query = Illuminate_Builder::new()->select('*')->fromSub($og_outer_query, 'records')->tap(fn(Builder $query) => $this->apply_or_filters($query))->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        }
        return $outer_query;
    }
}
