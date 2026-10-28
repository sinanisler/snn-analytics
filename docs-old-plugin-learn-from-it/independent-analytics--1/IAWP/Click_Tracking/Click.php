<?php

namespace IAWP\Click_Tracking;

use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Click
{
    private $protocol;
    private $value;
    private $href;
    private $classes;
    private $ids;
    private $resource_id;
    private $visitor_id;
    private $created_at;
    private function __construct(array $record)
    {
        $this->protocol = $this->extract_protocol_from_href($record['href']);
        $this->value = $this->extract_target_value_from_href($record['href']);
        $this->href = $record['href'];
        $this->classes = $record['classes'];
        $this->ids = $record['ids'];
        $this->resource_id = $record['resource_id'];
        $this->visitor_id = $record['visitor_id'];
        $this->created_at = $record['created_at'];
    }
    /**
     * Update the database
     *
     * @return void
     */
    public function track() : void
    {
        $link_rules = \IAWP\Click_Tracking\Link_Rule_Finder::new($this->protocol, $this->href, $this->classes, $this->ids)->links();
        if ($link_rules->isEmpty()) {
            return;
        }
        $view_id = $this->get_view_id();
        if (\is_null($view_id)) {
            return;
        }
        $click_target = $this->get_click_target();
        $link_ids = $link_rules->map(function (\IAWP\Click_Tracking\Link_Rule $link_rule) use($click_target) {
            return $this->get_link_id_for($link_rule->id(), $click_target->click_target_id);
        });
        $click_id = Illuminate_Builder::new()->from(Tables::clicks())->insertGetId(['view_id' => $view_id, 'created_at' => $this->created_at->format('Y-m-d H:i:s')]);
        $link_ids->each(function ($link_id) use($click_id) {
            Illuminate_Builder::new()->from(Tables::clicked_links())->insertGetId(['click_id' => $click_id, 'link_id' => $link_id]);
        });
    }
    private function extract_protocol_from_href(?string $href) : ?string
    {
        if (\is_null($href)) {
            return null;
        }
        if (Str::startsWith($href, ['tel:', 'sms:', 'mailto:'])) {
            return Str::before($href, ':');
        }
        return null;
    }
    private function extract_target_value_from_href(?string $href) : ?string
    {
        if (\is_null($href)) {
            return null;
        }
        if (Str::startsWith($href, ['tel:', 'sms:', 'mailto:'])) {
            return Str::after($href, ':');
        }
        return $href;
    }
    private function get_click_target() : object
    {
        $select_query = Illuminate_Builder::new()->from(Tables::click_targets())->where('target', '=', $this->value)->when(\is_null($this->protocol), function (Builder $query) {
            $query->whereNull('protocol');
        })->when(\is_string($this->protocol), function (Builder $query) {
            $query->where('protocol', '=', $this->protocol);
        });
        $match = $select_query->first();
        if (\is_object($match)) {
            return $match;
        }
        Illuminate_Builder::new()->from(Tables::click_targets())->insertOrIgnore(['target' => $this->value, 'protocol' => $this->protocol]);
        return $select_query->first();
    }
    private function get_view_id() : ?int
    {
        $sessions_table = Tables::sessions();
        $view_id = Illuminate_Builder::new()->from(Tables::views(), 'views')->join("{$sessions_table} AS sessions", 'views.session_id', '=', 'sessions.session_id')->where('views.resource_id', '=', $this->resource_id)->where('sessions.visitor_id', '=', $this->visitor_id)->where('views.viewed_at', '<=', $this->created_at->format('Y-m-d H:i:s'))->orderByDesc('views.viewed_at')->limit(1)->value('views.id');
        // There's a small chance that view_id is a string instead of an int. In that case,
        // it should be converted to a string.
        // https://github.com/andrewjmead/independent-analytics/issues/1335
        if (\is_string($view_id)) {
            return (int) $view_id;
        }
        if (\is_int($view_id)) {
            return $view_id;
        }
        return null;
    }
    private function get_link_id_for(int $link_rule_id, int $click_target_id) : int
    {
        $select_query = Illuminate_Builder::new()->from(Tables::links())->where('link_rule_id', '=', $link_rule_id)->where('click_target_id', '=', $click_target_id);
        $match = $select_query->first();
        if (\is_object($match)) {
            return $match->id;
        }
        Illuminate_Builder::new()->from(Tables::links())->insertOrIgnore(['link_rule_id' => $link_rule_id, 'click_target_id' => $click_target_id]);
        return $select_query->first()->id;
    }
    public static function new(array $record) : ?self
    {
        return new self($record);
    }
}
