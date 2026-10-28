<?php

namespace IAWP\Rows;

use IAWP\Illuminate_Builder;
use IAWP\Models\Link_Pattern;
use IAWP\Query_Taps;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class Link_Patterns extends \IAWP\Rows\Rows
{
    public function attach_filters(Builder $query) : void
    {
        $query->joinSub($this->query(\true), 'click_rows', function (JoinClause $join) {
            $join->on('click_rows.link_rule_id', '=', 'link_rules.link_rule_id');
        });
    }
    protected function fetch_rows() : array
    {
        $rows = $this->query()->get()->all();
        return \array_map(function ($row) {
            return new Link_Pattern($row);
        }, $rows);
    }
    protected function sort_tie_breaker_column() : string
    {
        return 'link_name';
    }
    private function query(?bool $skip_pagination = \false) : Builder
    {
        if ($skip_pagination) {
            $this->number_of_rows = null;
        }
        $records = Illuminate_Builder::new()->select(['link_rules.link_rule_id AS link_rule_id', 'link_rules.name AS link_name'])->selectRaw('COUNT(DISTINCT clicks.click_id) AS link_clicks')->from($this->tables::link_rules(), 'link_rules')->leftJoin($this->tables::links() . ' AS links', 'links.link_rule_id', '=', 'link_rules.link_rule_id')->leftJoin($this->tables::click_targets() . ' AS click_targets', 'click_targets.click_target_id', '=', 'links.click_target_id')->leftJoin($this->tables::clicked_links() . ' AS clicked_links', 'clicked_links.link_id', '=', 'links.id')->leftJoin($this->tables::clicks() . ' AS clicks', 'clicks.click_id', '=', 'clicked_links.click_id')->whereBetween('clicks.created_at', $this->get_current_period_iso_range())->when(\is_int($this->solo_record_id), function (Builder $query) {
            $query->where('link_rules.link_rule_id', '=', $this->solo_record_id);
        })->when(\is_int($this->number_of_rows), function (Builder $query) {
            $query->limit($this->number_of_rows);
        })->tap(Query_Taps::tap_authored_content_for_clicks())->when($this->examiner_config, function (Builder $query) {
            $query->leftJoin($this->tables::views() . ' AS views', 'views.id', '=', 'clicks.view_id');
            $query->leftJoin($this->tables::sessions() . ' AS sessions', 'sessions.session_id', '=', 'views.session_id');
            $query->tap(Query_Taps::tap_related_to_examined_record($this->examiner_config));
        })->orderBy($this->sort_configuration->column(), $this->sort_configuration->direction())->orderBy('link_rules.name')->groupBy('link_rules.link_rule_id')->tap(fn(Builder $query) => $this->apply_record_filters($query));
        $outer_query = Illuminate_Builder::new()->select('*')->fromSub($records, 'records')->tap(fn(Builder $query) => $this->apply_aggregate_filters($query));
        if ($this->using_logical_or_operator() && $this->filtering_by_mixed_columns()) {
            $og_outer_query = $outer_query;
            $outer_query = Illuminate_Builder::new()->select('*')->fromSub($og_outer_query, 'records')->tap(fn(Builder $query) => $this->apply_or_filters($query))->tap(fn(Builder $query) => $this->apply_order_and_limit($query, $this->sort_configuration->column()));
        }
        return $outer_query;
    }
}
