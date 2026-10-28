<?php

namespace IAWP\Rows;

use IAWP\Illuminate_Builder;
use IAWP\Models\Journey;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
/** @internal */
class Journeys extends \IAWP\Rows\Rows
{
    private ?int $visitor_id = null;
    public function attach_filters(Builder $query) : void
    {
        // TODO
    }
    public function limit_to_visitor(int $id) : void
    {
        $this->visitor_id = $id;
    }
    protected function fetch_rows() : array
    {
        $rows = $this->query()->get()->all();
        return \array_map(function ($row) {
            return new Journey($row);
        }, $rows);
    }
    protected function sort_tie_breaker_column() : string
    {
        return 'cached_title';
    }
    private function query(?bool $skip_pagination = \false) : Builder
    {
        if ($skip_pagination) {
            $this->number_of_rows = null;
        }
        $sort_column = $this->sort_configuration->column();
        $click_conversion_subquery = Illuminate_Builder::new()->select(['sessions.session_id', 'clicks.click_id', 'link_rules.link_rule_id', 'link_rules.name'])->from(Tables::sessions(), 'sessions')->join(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->join(Tables::clicks() . ' AS clicks', 'views.id', '=', 'clicks.view_id')->join(Tables::clicked_links() . ' AS clicked_links', 'clicks.click_id', '=', 'clicked_links.click_id')->join(Tables::links() . ' AS links', 'clicked_links.link_id', '=', 'links.id')->join(Tables::link_rules() . ' AS link_rules', 'links.link_rule_id', '=', 'link_rules.link_rule_id')->whereBetween('sessions.created_at', $this->get_current_period_iso_range());
        $orders_subquery = Illuminate_Builder::new()->select(['sessions.session_id'])->selectRaw('COUNT(DISTINCT orders.order_id) AS orders')->selectRaw('IFNULL(CAST(SUM(orders.total) AS SIGNED), 0) AS wc_gross_sales')->selectRaw('IFNULL(CAST(SUM(orders.total_refunded) AS SIGNED), 0) AS wc_refunded_amount')->from(Tables::sessions(), 'sessions')->join(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->join(Tables::orders() . ' AS orders', 'views.id', '=', 'orders.view_id')->whereBetween('sessions.created_at', $this->get_current_period_iso_range())->groupBy('sessions.session_id');
        $journeys_query = Illuminate_Builder::new()->select(['sessions.session_id', 'sessions.created_at', 'initial_resources.cached_title', 'initial_resources.cached_url', 'referrers.referrer', 'referrers.domain', 'referrer_types.referrer_type', 'countries.country_id', 'countries.country_code', 'countries.country', 'device_types.device_type_id', 'device_types.device_type', 'device_browsers.device_browser_id', 'device_browsers.device_browser', 'utm_sources.utm_source'])->selectRaw('TIME_TO_SEC(TIMEDIFF(sessions.ended_at, sessions.created_at)) AS duration')->selectRaw('COUNT(DISTINCT views.id) AS views')->selectRaw('COUNT(DISTINCT clicks.click_id) AS clicks')->selectRaw('COUNT(DISTINCT form_submissions.form_submission_id) AS form_submissions')->selectRaw('MIN(orders.orders) AS orders')->selectRaw('MIN(orders.wc_gross_sales) AS wc_gross_sales')->selectRaw('MIN(orders.wc_refunded_amount) AS wc_refunded_amount')->from(Tables::sessions(), 'sessions')->leftJoin(Tables::views() . ' AS initial_views', 'sessions.initial_view_id', '=', 'initial_views.id')->leftJoin(Tables::resources() . ' AS initial_resources', 'initial_views.resource_id', '=', 'initial_resources.id')->leftJoin(Tables::referrers() . ' AS referrers', 'sessions.referrer_id', '=', 'referrers.id')->leftJoin(Tables::referrer_types() . ' AS referrer_types', 'referrers.referrer_type_id', '=', 'referrer_types.id')->leftJoin(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->when($this->has_page_filter(), function ($query) {
            $query->leftJoin(Tables::resources() . ' AS resources', 'views.resource_id', '=', 'resources.id');
        })->leftJoin(Tables::countries() . ' AS countries', 'sessions.country_id', '=', 'countries.country_id')->leftJoin(Tables::device_types() . ' AS device_types', 'sessions.device_type_id', '=', 'device_types.device_type_id')->leftJoin(Tables::device_browsers() . ' AS device_browsers', 'sessions.device_browser_id', '=', 'device_browsers.device_browser_id')->leftJoin(Tables::campaigns() . ' AS campaigns', 'sessions.campaign_id', '=', 'campaigns.campaign_id')->leftJoin(Tables::utm_campaigns() . ' AS utm_campaigns', 'campaigns.utm_campaign_id', '=', 'utm_campaigns.id')->leftJoin(Tables::utm_mediums() . ' AS utm_mediums', 'campaigns.utm_medium_id', '=', 'utm_mediums.id')->leftJoin(Tables::utm_sources() . ' AS utm_sources', 'campaigns.utm_source_id', '=', 'utm_sources.id')->leftJoin(Tables::orders() . ' AS orders', 'views.id', '=', 'orders.view_id')->leftJoin(Tables::form_submissions() . ' AS form_submissions', 'sessions.session_id', '=', 'form_submissions.session_id')->leftJoinSub($click_conversion_subquery, 'clicks', 'sessions.session_id', '=', 'clicks.session_id')->leftJoinSub($orders_subquery, 'orders', 'sessions.session_id', '=', 'orders.session_id')->whereBetween('sessions.created_at', $this->get_current_period_iso_range())->when(\is_int($this->visitor_id), function (Builder $query) {
            $query->where('sessions.visitor_id', '=', $this->visitor_id);
        })->tap(fn(Builder $query) => $this->apply_record_filters($query))->when($this->can_order_and_limit_at_record_level(), function (Builder $query) use($sort_column) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $sort_column));
        })->groupBy('sessions.session_id');
        $outer_query = Illuminate_Builder::new()->fromSub($journeys_query, 'journeys')->tap(fn(Builder $query) => $this->apply_aggregate_filters($query))->when(!$this->can_order_and_limit_at_record_level() && !($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()), function (Builder $query) use($sort_column) {
            $query->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $sort_column));
        });
        if ($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()) {
            $og_outer_query = $outer_query;
            $outer_query = Illuminate_Builder::new()->select('*')->fromSub($og_outer_query, 'records')->tap(fn(Builder $query) => $this->apply_or_filters($query))->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $sort_column));
        }
        return $outer_query;
    }
    private function has_page_filter() : bool
    {
        foreach ($this->filters as $filter) {
            if ($filter->column() === 'page') {
                return \true;
            }
        }
        return \false;
    }
}
