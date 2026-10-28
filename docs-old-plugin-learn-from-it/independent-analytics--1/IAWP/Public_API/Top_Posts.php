<?php

namespace IAWP\Public_API;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class Top_Posts
{
    public $options = [];
    public function __construct(array $options = [])
    {
        $this->options = $this->validate_options($options);
    }
    public function get() : array
    {
        $date_range = new Exact_Date_Range($this->options['from']->toDateTime(), $this->options['to']->toDateTime());
        $resources_table = Query::get_table_name(Query::RESOURCES);
        $views_table = Query::get_table_name(Query::VIEWS);
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $resource_statistics_query = Illuminate_Builder::new();
        $resource_statistics_query->selectRaw('resources.singular_id AS id')->selectRaw('resources.cached_title AS title')->selectRaw('COUNT(DISTINCT views.id) AS views')->selectRaw('COUNT(DISTINCT sessions.visitor_id) AS visitors')->selectRaw('COUNT(DISTINCT sessions.session_id) AS sessions')->from("{$views_table} as views")->join("{$resources_table} AS resources", function (JoinClause $join) {
            $join->on('resources.id', '=', 'views.resource_id');
        })->join("{$sessions_table} AS sessions", function (JoinClause $join) {
            $join->on('sessions.session_id', '=', 'views.session_id');
        })->whereNotNull('resources.singular_id')->where('resources.cached_type', '=', $this->options['post_type'])->when(\is_int($this->options['category']), function (Builder $query) {
            $query->whereRaw("find_in_set(?, REPLACE(cached_category, ', ', ','))", [$this->options['category']]);
        })->whereBetween('views.viewed_at', [$date_range->iso_start(), $date_range->iso_end()])->limit($this->options['limit'])->orderByDesc($this->options['sort_by'])->groupBy('resources.id');
        $results = $resource_statistics_query->get()->toArray();
        return $results;
    }
    private function validate_options(array $options) : array
    {
        // Post type
        if (\array_key_exists('post_type', $options) && \post_type_exists($options['post_type'])) {
            $post_type = $options['post_type'];
        } else {
            $post_type = 'post';
        }
        // Category
        if (\array_key_exists('category', $options) && \is_int($options['category'])) {
            $category = $options['category'];
        } else {
            $category = null;
        }
        // Limit
        if (\array_key_exists('limit', $options) && \is_int($options['limit'])) {
            $limit = $options['limit'];
        } else {
            $limit = 10;
        }
        // From
        if (\array_key_exists('from', $options) && $options['from'] instanceof \DateTime) {
            $from = CarbonImmutable::instance($options['from']);
        } else {
            $from = CarbonImmutable::now()->subtract('days', 30);
        }
        // To
        if (\array_key_exists('to', $options) && $options['to'] instanceof \DateTime) {
            $to = CarbonImmutable::instance($options['to']);
        } else {
            $to = CarbonImmutable::now();
        }
        // Is date range valid?
        if ($from->greaterThanOrEqualTo($to)) {
            $from = CarbonImmutable::now()->subtract('days', 30);
            $to = CarbonImmutable::now();
        }
        // Sort by
        $valid_sorting_options = ['views', 'visitors', 'sessions'];
        if (\array_key_exists('sort_by', $options) && \in_array($options['sort_by'], $valid_sorting_options)) {
            $sort_by = $options['sort_by'];
        } else {
            $sort_by = 'views';
        }
        return ['post_type' => $post_type, 'category' => $category, 'limit' => $limit, 'from' => $from, 'to' => $to, 'sort_by' => $sort_by];
    }
}
